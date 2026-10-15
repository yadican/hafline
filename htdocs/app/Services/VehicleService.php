<?php

namespace App\Services;

use App\Enums\PartyRole;
use App\Models\Party;
use App\Models\Vehicle;
use App\Repositories\PartyRepository;
use App\Repositories\VehicleClassRepository;
use App\Repositories\VehicleRepository;
use App\Support\Sort;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

class VehicleService extends Service
{
    public function __construct(
        private VehicleRepository $vehicles,
        private PartyRepository $parties,
        private VehicleClassRepository $classes,
        private VehicleOwnershipService $ownerships,
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
            'owners' => $this->parties->options(PartyRole::VehicleOwner),
            'classes' => $this->classes->options(),
        ];
    }

    /**
     * Any active party can become an owner; the role is added on assignment.
     *
     * @return array{parties: array<string, string>, classes: array<string, string>}
     */
    public function formOptions(?Vehicle $vehicle = null): array
    {
        return [
            'parties' => $this->parties->options(),
            'classes' => $this->classes->options(activeOnly: true, include: $vehicle?->vehicle_class_id),
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Vehicle
    {
        return $this->transaction(function () use ($attributes): Vehicle {
            /** @var Vehicle $vehicle */
            $vehicle = $this->vehicles->create(Arr::except($attributes, ['owner_party_id', 'owned_from']));

            $this->ownerships->open(
                $vehicle,
                Party::query()->findOrFail($attributes['owner_party_id']),
                Carbon::parse($attributes['owned_from']),
            );

            return $vehicle;
        });
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
