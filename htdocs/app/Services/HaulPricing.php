<?php

namespace App\Services;

use App\Models\DumpSiteClassPrice;
use App\Models\HaulRoute;
use App\Models\Vehicle;

class HaulPricing
{
    /**
     * A vehicle-specific route price wins over the vehicle class price; missing prices count as zero.
     *
     * @return array{price: string, dump_price: string}
     */
    public function quote(Vehicle $vehicle, HaulRoute $route): array
    {
        $price = $route->vehiclePrices()->where('vehicle_id', $vehicle->id)->value('price')
            ?? $route->classPrices()->where('vehicle_class_id', $vehicle->vehicle_class_id)->value('price');

        $dumpPrice = DumpSiteClassPrice::query()
            ->where('dump_site_id', $route->dump_site_id)
            ->where('vehicle_class_id', $vehicle->vehicle_class_id)
            ->value('price');

        return [
            'price' => (string) ($price ?? 0),
            'dump_price' => (string) ($dumpPrice ?? 0),
        ];
    }
}
