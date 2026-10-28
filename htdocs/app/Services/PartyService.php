<?php

namespace App\Services;

use App\Cache\FormOptionsCache;
use App\Cache\PartyCache;
use App\Enums\PartyRole;
use App\Enums\PartyType;
use App\Exceptions\OperationRejectedException;
use App\Models\Party;
use App\Repositories\LocationRepository;
use App\Repositories\PartyRepository;
use App\Support\Sort;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PartyService extends Service
{
    private const PARTY_FIELDS = ['phone', 'mobile', 'email', 'city_id', 'district_id', 'neighborhood_id', 'address', 'notes', 'active'];

    private const TEMPORARY_NAME = 'GEÇİCİ CARİ';

    public function __construct(
        private PartyRepository $parties,
        private LocationRepository $locations,
        private EmployeeService $employees,
    ) {}

    /**
     * Option lists for the location selects; districts and neighborhoods follow the selected parent.
     *
     * @return array{cities: array<string, string>, districts: array<string, string>, neighborhoods: array<string, string>}
     */
    public function locationOptions(?string $cityId, ?string $districtId): array
    {
        return [
            'cities' => $this->locations->cities(),
            'districts' => $this->locations->districts($cityId),
            'neighborhoods' => $this->locations->neighborhoods($districtId),
        ];
    }

    /**
     * @param  array{q?: string, type?: string, role?: string, active?: string}  $filters
     */
    public function paginate(array $filters = [], ?Sort $sort = null, int $perPage = 20): LengthAwarePaginator
    {
        return PartyCache::rememberFirstPage(
            $filters,
            $sort,
            fn () => $this->parties->search($filters, $sort, $perPage),
        );
    }

    /**
     * @return array<string, string>
     */
    public function options(?PartyRole $role = null, ?string $include = null): array
    {
        return $this->parties->options($role, $include);
    }

    public function statement(Party $party, bool $withReversals = false): LengthAwarePaginator
    {
        return $this->parties->statement($party, withReversals: $withReversals);
    }

    public function hasReversals(Party $party): bool
    {
        return $this->parties->hasReversals($party);
    }

    /**
     * @return array{debit: float, credit: float, balance: float}
     */
    public function totals(Party $party): array
    {
        return $this->parties->totals($party);
    }

    /**
     * @return list<PartyRole>
     */
    public function lockedRoles(Party $party): array
    {
        return $this->parties->lockedRoles($party);
    }

    /**
     * Flat field values for the edit form, merging the party with its person/company details.
     *
     * @return array<string, mixed>
     */
    public function formValues(Party $party): array
    {
        $party->loadMissing(['person', 'company', 'roleAssignments']);

        return [
            ...$party->only(self::PARTY_FIELDS),
            ...($party->person?->only(['first_name', 'last_name', 'national_id']) ?? []),
            ...($party->company?->only(['title', 'tax_office', 'tax_number', 'fax']) ?? []),
            'roles' => $party->roles,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(PartyType $type, array $data): Party
    {
        return $this->transaction(function () use ($type, $data): Party {
            /** @var Party $party */
            $party = $this->parties->create([
                ...$this->partyAttributes($type, $data),
                'type' => $type,
            ]);

            $type === PartyType::Person
                ? $party->person()->create($this->personAttributes($data))
                : $party->company()->create($this->companyAttributes($data));

            $this->syncRoles($party, $data['roles'] ?? []);
            $this->employees->ensureForParty($party->fresh(['roleAssignments']));

            $this->flushCaches();

            return $party;
        });
    }

    /**
     * The system's own cari for unregistered plates; created on first use.
     */
    public function temporaryParty(): Party
    {
        $existing = Party::query()->where('system_key', Party::TEMPORARY)->first();

        if ($existing) {
            return $existing;
        }

        return $this->transaction(function (): Party {
            $party = new Party(['name' => self::TEMPORARY_NAME, 'active' => true]);
            $party->forceFill(['type' => PartyType::Company, 'system_key' => Party::TEMPORARY])->save();
            $party->company()->create(['title' => self::TEMPORARY_NAME]);
            $party->assignRole(PartyRole::VehicleOwner);

            $this->flushCaches();

            return $party;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Party $party, array $data): Party
    {
        if ($party->isSystem() && ($this->partyAttributes($party->type, $data)['name'] !== $party->name || ! ($data['active'] ?? false))) {
            throw new OperationRejectedException("{$party->name} sistem carisidir; adı değiştirilemez ve pasife alınamaz.");
        }

        return $this->transaction(function () use ($party, $data): Party {
            /** @var Party $updated */
            $updated = $this->parties->update($party, $this->partyAttributes($party->type, $data));

            $party->type === PartyType::Person
                ? $party->person()->updateOrCreate([], $this->personAttributes($data))
                : $party->company()->updateOrCreate([], $this->companyAttributes($data));

            $this->syncRoles($updated, $data['roles'] ?? []);
            $this->employees->ensureForParty($updated->fresh(['roleAssignments']));

            $this->flushCaches();

            return $updated;
        });
    }

    public function delete(Party $party): void
    {
        if ($party->isSystem()) {
            throw new OperationRejectedException("{$party->name} sistem carisidir; silinemez.");
        }

        if ($this->parties->isReferenced($party)) {
            throw new OperationRejectedException('Bu cari kartın araç, döküm yeri veya cari hareket kaydı var; silinemez. Kullanılmayacaksa pasife alın.');
        }

        $this->transaction(function () use ($party): void {
            $this->parties->delete($party);
            $this->flushCaches();
        });
    }

    private function flushCaches(): void
    {
        PartyCache::flush();
        FormOptionsCache::flush(FormOptionsCache::PARTIES, FormOptionsCache::EMPLOYEES);
    }

    /**
     * Roles that records depend on are kept even if unticked.
     *
     * @param  list<string>  $requested
     */
    private function syncRoles(Party $party, array $requested): void
    {
        $roles = collect($requested)
            ->map(fn (string $role) => PartyRole::from($role))
            ->filter(fn (PartyRole $role) => $role->allowedFor($party->type))
            ->merge($this->parties->lockedRoles($party))
            ->map(fn (PartyRole $role) => $role->value)
            ->unique();

        $party->roleAssignments()->whereNotIn('role', $roles->all())->delete();

        foreach ($roles as $role) {
            $party->roleAssignments()->firstOrCreate(['role' => $role]);
        }

        $party->unsetRelation('roleAssignments');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function partyAttributes(PartyType $type, array $data): array
    {
        $name = $type === PartyType::Person
            ? trim($data['first_name'].' '.$data['last_name'])
            : trim($data['title']);

        return [
            ...array_intersect_key($data, array_flip(self::PARTY_FIELDS)),
            'name' => $name,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function personAttributes(array $data): array
    {
        return array_intersect_key($data, array_flip(['first_name', 'last_name', 'national_id']));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function companyAttributes(array $data): array
    {
        return array_intersect_key($data, array_flip(['title', 'tax_office', 'tax_number', 'fax']));
    }
}
