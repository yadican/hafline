<?php

namespace App\Services;

use App\Cache\FormOptionsCache;
use App\Enums\PartyRole;
use App\Enums\PartyType;
use App\Exceptions\OperationRejectedException;
use App\Models\Quarry;
use App\Models\Shipment;
use App\Repositories\PartyRepository;
use App\Repositories\QuarryRepository;
use App\Support\Sort;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class QuarryService extends Service
{
    public function __construct(
        private QuarryRepository $quarries,
        private PartyRepository $parties,
    ) {}

    /**
     * @param  array{q?: string, active?: string}  $filters
     */
    public function paginate(array $filters = [], ?Sort $sort = null, int $perPage = 20): LengthAwarePaginator
    {
        return $this->quarries->search($filters, $sort, $perPage);
    }

    /**
     * @return array<string, string>
     */
    public function partyOptions(?Quarry $quarry = null): array
    {
        return $this->parties->options(include: $quarry?->party_id, type: PartyType::Company);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Quarry
    {
        return $this->transaction(function () use ($attributes): Quarry {
            /** @var Quarry $quarry */
            $quarry = $this->quarries->create($attributes);
            $this->assignRole($quarry);

            return $quarry;
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Quarry $quarry, array $attributes): Quarry
    {
        return $this->transaction(function () use ($quarry, $attributes): Quarry {
            /** @var Quarry $updated */
            $updated = $this->quarries->update($quarry, $attributes);
            $this->assignRole($updated);

            return $updated;
        });
    }

    public function delete(Quarry $quarry): void
    {
        if (Shipment::query()->where('quarry_id', $quarry->id)->exists()) {
            throw new OperationRejectedException('Bu ocaktan sefer girilmiş; silmek yerine pasife alın.');
        }

        $this->transaction(fn () => $this->quarries->delete($quarry));
    }

    private function assignRole(Quarry $quarry): void
    {
        if ($quarry->party) {
            $quarry->party->assignRole(PartyRole::Quarry);
            FormOptionsCache::flush(FormOptionsCache::PARTIES);
        }
    }
}
