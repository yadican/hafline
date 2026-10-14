<?php

namespace App\Services;

use App\Enums\Shift;
use App\Exceptions\OperationException;
use App\Models\Haul;
use App\Models\HaulRoute;
use App\Models\Vehicle;
use App\Repositories\HaulLookupRepository;
use App\Repositories\HaulRepository;
use App\Support\Sort;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class HaulService extends Service
{
    public function __construct(
        private HaulRepository $hauls,
        private HaulLookupRepository $lookups,
        private HaulPricing $pricing,
    ) {}

    /**
     * @param  array<string, string>  $filters
     */
    public function paginate(array $filters, ?Sort $sort = null, int $perPage = 30): LengthAwarePaginator
    {
        return $this->hauls->search($filters, $sort, $perPage);
    }

    /**
     * @param  array<string, string>  $filters
     * @return array{price: float, tonnage: int}
     */
    public function totals(array $filters): array
    {
        return $this->hauls->totals($filters);
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function searchOptions(): array
    {
        return [
            'sites' => $this->lookups->sites(),
            'dumpSites' => $this->lookups->dumpSites(),
            'owners' => $this->lookups->owners(),
            'classes' => $this->lookups->vehicleClasses(),
            'creators' => $this->lookups->creators(),
            'shifts' => Shift::options(),
        ];
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function formOptions(?string $siteId, ?Haul $haul = null): array
    {
        return [
            'sites' => $this->lookups->sites(activeOnly: true, include: $haul?->site_id),
            'dumpSites' => $this->lookups->dumpSitesForSite($siteId),
            'shifts' => Shift::options(),
        ];
    }

    public function ticketTaken(string $ticketNo): bool
    {
        return $this->hauls->ticketTaken($ticketNo);
    }

    /**
     * Each trip is stored as its own haul under the same ticket number.
     *
     * @param  array<string, mixed>  $data
     * @return Collection<int, Haul>
     */
    public function create(array $data, ?string $userId): Collection
    {
        [$vehicle, $route] = $this->resolve($data);

        return $this->transaction(function () use ($data, $userId, $vehicle, $route): Collection {
            $attributes = [
                ...$this->attributes($data, $vehicle, $route),
                'owner_id' => $vehicle->owner_id,
                'driver_name' => $vehicle->driver_name,
                'created_by' => $userId,
            ];

            return collect(range(1, (int) $data['trips']))
                ->map(fn (): Haul => $this->hauls->create($attributes));
        });
    }

    /**
     * Prices are re-quoted with the current tariff, as in the legacy entry screen.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Haul $haul, array $data): Haul
    {
        [$vehicle, $route] = $this->resolve($data);

        return $this->transaction(function () use ($haul, $data, $vehicle, $route): Haul {
            $attributes = $this->attributes($data, $vehicle, $route);

            if ($haul->vehicle_id !== $vehicle->id) {
                $attributes['owner_id'] = $vehicle->owner_id;
                $attributes['driver_name'] = $vehicle->driver_name;
            }

            /** @var Haul $updated */
            $updated = $this->hauls->update($haul, $attributes);

            return $updated;
        });
    }

    public function delete(Haul $haul): void
    {
        $this->transaction(fn () => $this->hauls->delete($haul));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{Vehicle, HaulRoute}
     */
    private function resolve(array $data): array
    {
        $vehicle = Vehicle::findByPlate((string) $data['plate']);
        $route = HaulRoute::between((string) $data['site_id'], (string) $data['dump_site_id']);

        if (! $vehicle || ! $route) {
            throw new OperationException('Plaka veya güzergah bulunamadı.');
        }

        return [$vehicle, $route];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data, Vehicle $vehicle, HaulRoute $route): array
    {
        return [
            'ticket_no' => $data['ticket_no'],
            'vehicle_id' => $vehicle->id,
            'route_id' => $route->id,
            'site_id' => $route->site_id,
            'dump_site_id' => $route->dump_site_id,
            'tonnage' => (int) $data['tonnage'],
            'shift' => $data['shift'],
            'hauled_at' => $data['hauled_at'],
            ...$this->pricing->quote($vehicle, $route),
        ];
    }
}
