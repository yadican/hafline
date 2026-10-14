<?php

namespace App\Services;

use App\Models\Vehicle;
use App\Repositories\VehicleClassRepository;
use App\Repositories\VehicleOwnerRepository;
use App\Repositories\VehicleRepository;
use App\Support\Sort;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class VehicleService extends Service
{
    public function __construct(
        private VehicleRepository $vehicles,
        private VehicleOwnerRepository $owners,
        private VehicleClassRepository $classes,
    ) {}

    /**
     * @param  array{q?: string, owner?: string, class?: string, active?: string}  $filters
     */
    public function paginate(array $filters = [], ?Sort $sort = null, int $perPage = 15): LengthAwarePaginator
    {
        return $this->vehicles->search($filters, $sort, $perPage);
    }

    /**
     * @return array{owners: array<string, string>, classes: array<string, string>}
     */
    public function searchOptions(): array
    {
        return [
            'owners' => $this->owners->options(),
            'classes' => $this->classes->options(),
        ];
    }

    /**
     * @return array{owners: array<string, string>, classes: array<string, string>}
     */
    public function formOptions(?Vehicle $vehicle = null): array
    {
        return [
            'owners' => $this->owners->options(),
            'classes' => $this->classes->options(activeOnly: true, include: $vehicle?->vehicle_class_id),
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Vehicle
    {
        /** @var Vehicle */
        return $this->transaction(fn () => $this->vehicles->create($attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Vehicle $vehicle, array $attributes): Vehicle
    {
        /** @var Vehicle */
        return $this->transaction(fn () => $this->vehicles->update($vehicle, $attributes));
    }

    public function delete(Vehicle $vehicle): void
    {
        $this->transaction(fn () => $this->vehicles->delete($vehicle));
    }
}
