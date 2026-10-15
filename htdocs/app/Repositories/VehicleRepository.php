<?php

namespace App\Repositories;

use App\Models\Party;
use App\Models\Vehicle;
use App\Models\VehicleClass;
use App\Support\Sort;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class VehicleRepository extends Repository
{
    public function __construct(Vehicle $model)
    {
        parent::__construct($model);
    }

    /**
     * @param  array{q?: string, owner?: string, class?: string, active?: string}  $filters
     */
    public function search(array $filters, ?Sort $sort = null, int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->query()
            ->with(['currentOwnership.party', 'vehicleClass'])
            ->when($filters['q'] ?? null, fn (Builder $query, string $term) => $query->where(
                fn (Builder $query) => $query
                    ->whereLike('plate_key', $this->likePattern(Vehicle::normalizePlate($term)))
                    ->orWhereLike('driver_name', $this->likePattern($term)),
            ))
            ->when($filters['owner'] ?? null, fn (Builder $query, string $owner) => $query->whereHas('currentOwnership', fn (Builder $ownership) => $ownership->where('party_id', $owner)))
            ->when($filters['class'] ?? null, fn (Builder $query, string $class) => $query->where('vehicle_class_id', $class))
            ->when(isset($filters['active']), fn (Builder $query) => $query->where('active', $filters['active'] === '1'));

        return $this->applySort($query, $sort)
            ->paginate($perPage)
            ->withQueryString();
    }

    protected function defaultSort(Builder $query): void
    {
        $query->orderBy('plate_key');
    }

    protected function sortColumns(): array
    {
        return [
            'plate' => 'plate_key',
            'owner' => fn (Builder $query, string $direction) => $query->orderBy(
                Party::withTrashed()
                    ->select('parties.name')
                    ->join('vehicle_ownerships', 'vehicle_ownerships.party_id', '=', 'parties.id')
                    ->whereColumn('vehicle_ownerships.vehicle_id', 'vehicles.id')
                    ->whereNull('vehicle_ownerships.ended_on')
                    ->limit(1),
                $direction,
            ),
            'class' => fn (Builder $query, string $direction) => $query->orderBy(
                VehicleClass::withTrashed()->select('name')->whereColumn('vehicle_classes.id', 'vehicles.vehicle_class_id'),
                $direction,
            ),
            'driver' => 'driver_name',
        ];
    }
}
