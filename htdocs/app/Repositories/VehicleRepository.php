<?php

namespace App\Repositories;

use App\Models\Vehicle;
use App\Models\VehicleClass;
use App\Models\VehicleOwner;
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
            ->with(['owner', 'vehicleClass'])
            ->when($filters['q'] ?? null, fn (Builder $query, string $term) => $query->where(
                fn (Builder $query) => $query
                    ->whereLike('plate_key', $this->likePattern(Vehicle::normalizePlate($term)))
                    ->orWhereLike('driver_name', $this->likePattern($term)),
            ))
            ->when($filters['owner'] ?? null, fn (Builder $query, string $owner) => $query->where('owner_id', $owner))
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
            'owner' => fn (Builder $query, string $direction) => $query
                ->orderBy(VehicleOwner::withTrashed()->select('first_name')->whereColumn('vehicle_owners.id', 'vehicles.owner_id'), $direction)
                ->orderBy(VehicleOwner::withTrashed()->select('last_name')->whereColumn('vehicle_owners.id', 'vehicles.owner_id'), $direction),
            'class' => fn (Builder $query, string $direction) => $query->orderBy(
                VehicleClass::withTrashed()->select('name')->whereColumn('vehicle_classes.id', 'vehicles.vehicle_class_id'),
                $direction,
            ),
            'driver' => 'driver_name',
        ];
    }
}
