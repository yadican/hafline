<?php

namespace App\Services;

use App\Models\DumpSiteClassPrice;
use App\Models\HaulRoute;
use App\Models\Vehicle;
use Illuminate\Support\Arr;

class HaulPricing
{
    /**
     * A vehicle-specific route price wins over the vehicle class price; missing prices count as zero.
     *
     * @return array{price: string, dump_price: string, site_price: string}
     */
    public function quote(Vehicle $vehicle, HaulRoute $route): array
    {
        return Arr::only($this->explain($vehicle, $route), ['price', 'dump_price', 'site_price']);
    }

    /**
     * The quote together with where the trip price came from, for showing it before saving.
     *
     * @return array{price: string, dump_price: string, site_price: string, source: 'vehicle'|'class'|null, class: ?string, dump_priced: bool}
     */
    public function explain(Vehicle $vehicle, HaulRoute $route): array
    {
        $vehiclePrice = $route->vehiclePrices()->where('vehicle_id', $vehicle->id)->value('price');
        $classPrice = $vehiclePrice === null
            ? $route->classPrices()->where('vehicle_class_id', $vehicle->vehicle_class_id)->value('price')
            : null;

        $dumpPrice = DumpSiteClassPrice::query()
            ->where('dump_site_id', $route->dump_site_id)
            ->where('vehicle_class_id', $vehicle->vehicle_class_id)
            ->value('price');

        $sitePrice = $route->sitePrices()->where('vehicle_class_id', $vehicle->vehicle_class_id)->value('price');

        return [
            'price' => (string) ($vehiclePrice ?? $classPrice ?? 0),
            'dump_price' => (string) ($dumpPrice ?? 0),
            'site_price' => (string) ($sitePrice ?? 0),
            'source' => match (true) {
                $vehiclePrice !== null => 'vehicle',
                $classPrice !== null => 'class',
                default => null,
            },
            'class' => $vehicle->vehicleClass?->name,
            'dump_priced' => $dumpPrice !== null,
        ];
    }
}
