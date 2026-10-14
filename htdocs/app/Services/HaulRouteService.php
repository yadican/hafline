<?php

namespace App\Services;

use App\Models\HaulRoute;
use App\Models\RouteVehiclePrice;
use App\Models\Vehicle;
use App\Repositories\DumpSiteRepository;
use App\Repositories\HaulRouteRepository;
use App\Repositories\SiteRepository;
use App\Repositories\VehicleClassRepository;
use App\Services\Concerns\SyncsClassPrices;
use App\Support\Sort;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;

class HaulRouteService extends Service
{
    use SyncsClassPrices;

    public function __construct(
        private HaulRouteRepository $routes,
        private SiteRepository $sites,
        private DumpSiteRepository $dumpSites,
        private VehicleClassRepository $classes,
    ) {}

    /**
     * @param  array{site?: string, dump_site?: string, active?: string}  $filters
     */
    public function paginate(array $filters = [], ?Sort $sort = null, int $perPage = 15): LengthAwarePaginator
    {
        return $this->routes->search($filters, $sort, $perPage);
    }

    /**
     * @return array{sites: array<string, string>, dumpSites: array<string, string>}
     */
    public function searchOptions(): array
    {
        return [
            'sites' => $this->sites->options(),
            'dumpSites' => $this->dumpSites->options(),
        ];
    }

    /**
     * @return array{sites: array<string, string>, dumpSites: array<string, string>, classes: array<string, string>}
     */
    public function formOptions(?HaulRoute $route = null): array
    {
        return [
            'sites' => $this->sites->options(activeOnly: true, include: $route?->site_id),
            'dumpSites' => $this->dumpSites->options(activeOnly: true, include: $route?->dump_site_id),
            'classes' => $this->classes->options(activeOnly: true, include: $route?->classPrices->pluck('vehicle_class_id')->all()),
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): HaulRoute
    {
        $prices = Arr::pull($attributes, 'class_prices', []);

        /** @var HaulRoute */
        return $this->transaction(function () use ($attributes, $prices): HaulRoute {
            /** @var HaulRoute $route */
            $route = $this->routes->create($attributes);
            $this->syncClassPrices($route, 'classPrices', $prices);

            return $route;
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(HaulRoute $route, array $attributes): HaulRoute
    {
        $prices = Arr::pull($attributes, 'class_prices', []);

        /** @var HaulRoute */
        return $this->transaction(function () use ($route, $attributes, $prices): HaulRoute {
            /** @var HaulRoute $updated */
            $updated = $this->routes->update($route, $attributes);
            $this->syncClassPrices($updated, 'classPrices', $prices);

            return $updated;
        });
    }

    public function delete(HaulRoute $route): void
    {
        $this->transaction(fn () => $this->routes->delete($route));
    }

    /**
     * Setting a price for a vehicle that already has one replaces it.
     */
    public function setVehiclePrice(HaulRoute $route, Vehicle $vehicle, string $price): RouteVehiclePrice
    {
        /** @var RouteVehiclePrice */
        return $this->transaction(fn () => $route->vehiclePrices()->updateOrCreate(
            ['vehicle_id' => $vehicle->id],
            ['price' => $price],
        ));
    }

    public function removeVehiclePrice(RouteVehiclePrice $price): void
    {
        $this->transaction(fn () => $price->delete());
    }
}
