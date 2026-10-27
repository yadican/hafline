<?php

namespace App\Services;

use App\Enums\PartyRole;
use App\Enums\Shift;
use App\Exceptions\OperationException;
use App\Exceptions\OperationRejectedException;
use App\Models\MachineWork;
use App\Models\Party;
use App\Models\Vehicle;
use App\Repositories\HaulLookupRepository;
use App\Repositories\MachineWorkRepository;
use App\Repositories\PartyRepository;
use App\Repositories\SiteRepository;
use App\Services\Ledger\MachineWorkLedger;
use App\Support\Sort;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class MachineWorkService extends Service
{
    public function __construct(
        private MachineWorkRepository $works,
        private SiteRepository $sites,
        private PartyRepository $parties,
        private HaulLookupRepository $lookups,
        private MachineWorkLedger $ledger,
    ) {}

    /**
     * @param  array<string, string>  $filters
     */
    public function paginate(array $filters, ?Sort $sort = null, int $perPage = 20): LengthAwarePaginator
    {
        return $this->works->search($filters, $sort, $perPage);
    }

    /**
     * @param  array<string, string>  $filters
     * @return array{hours: float, amount: float}
     */
    public function totals(array $filters): array
    {
        return $this->works->totals($filters);
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function searchOptions(): array
    {
        return [
            'sites' => $this->lookups->sites(),
            'owners' => $this->lookups->owners(),
            'operators' => $this->parties->options(PartyRole::MachineOperator),
            'creators' => $this->lookups->creators(),
            'shifts' => Shift::options(),
        ];
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function formOptions(?MachineWork $work = null): array
    {
        return [
            'sites' => $this->sites->options(activeOnly: true, include: $work?->site_id),
            'operators' => $this->operatorOptions($work),
            'shifts' => Shift::options(),
        ];
    }

    /**
     * Active parties with the machine operator role, plus the one already on the ticket.
     *
     * @return array<string, string>
     */
    public function operatorOptions(?MachineWork $work = null): array
    {
        return $this->parties->options(PartyRole::MachineOperator, $work?->operator_id);
    }

    /**
     * @return Collection<int, Vehicle>
     */
    public function machines(string $term): Collection
    {
        return $this->works->machines($term);
    }

    /**
     * Staff (parties with the employee role) are on a salary, so tickets never credit them.
     */
    public function isSalaried(Party $operator): bool
    {
        return $operator->hasRole(PartyRole::Employee);
    }

    /**
     * The rate this operator was paid on their latest ticket, to prefill the next one.
     */
    public function lastOperatorRate(Party $operator): ?string
    {
        return $this->works->lastOperatorRate($operator->id);
    }

    public function ticketTaken(string $ticketNo): bool
    {
        return $this->works->ticketTaken($ticketNo);
    }

    public function ownerOn(Vehicle $vehicle, CarbonInterface $date): ?string
    {
        return $vehicle->ownershipOn($date)?->party_id;
    }

    public function rateOn(Vehicle $vehicle, CarbonInterface $date): ?string
    {
        return $vehicle->rateOn($date)?->hourly_rate;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?string $userId): MachineWork
    {
        $vehicle = $this->resolve($data);

        return $this->transaction(function () use ($data, $userId, $vehicle): MachineWork {
            /** @var MachineWork $work */
            $work = $this->works->create([...$this->attributes($data, $vehicle), 'created_by' => $userId]);
            $this->ledger->sync($work);

            return $work;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(MachineWork $work, array $data): MachineWork
    {
        $vehicle = $this->resolve($data);

        return $this->transaction(function () use ($work, $data, $vehicle): MachineWork {
            /** @var MachineWork $updated */
            $updated = $this->works->update($work, $this->attributes($data, $vehicle));
            $this->ledger->sync($updated);

            return $updated;
        });
    }

    public function delete(MachineWork $work): void
    {
        $this->transaction(function () use ($work): void {
            $this->ledger->void($work);
            $this->works->delete($work);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolve(array $data): Vehicle
    {
        return Vehicle::findByPlate((string) $data['plate'])
            ?? throw new OperationException('Plaka bulunamadı.');
    }

    /**
     * A blank rate falls back to the machine's rate period covering the work date.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data, Vehicle $vehicle): array
    {
        $workedOn = Carbon::parse($data['worked_on'])->startOfDay();
        $party = $this->ownerOn($vehicle, $workedOn);
        $rate = $data['hourly_rate'] ?? $this->rateOn($vehicle, $workedOn);
        $operator = empty($data['operator_id']) ? null : Party::query()->find($data['operator_id']);

        if (! $party) {
            throw new OperationRejectedException(sprintf('%s aracının %s tarihinde kayıtlı sahibi yok.', $vehicle->plate, $workedOn->format('d.m.Y')));
        }

        if ($rate === null) {
            throw new OperationRejectedException(sprintf('%s için %s tarihinde saat ücreti tanımlı değil.', $vehicle->plate, $workedOn->format('d.m.Y')));
        }

        return [
            'ticket_no' => $data['ticket_no'],
            'vehicle_id' => $vehicle->id,
            'party_id' => $party,
            'site_id' => $data['site_id'],
            'operator_id' => $operator?->id,
            'operator_rate' => $operator && ! $this->isSalaried($operator) ? ($data['operator_rate'] ?? null) : null,
            'shift' => $data['shift'],
            'hours' => $data['hours'],
            'hourly_rate' => $rate,
            'worked_on' => $workedOn->toDateString(),
        ];
    }
}
