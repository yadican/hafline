<?php

namespace App\Services;

use App\Enums\ShipmentKind;
use App\Enums\TransportPriceKind;
use App\Models\TransportPrice;
use Carbon\CarbonInterface;

/**
 * Default unit prices for a shipment from the price lists valid on its date.
 */
class ShipmentPricing
{
    /**
     * @return array{sale_price: ?string, carrier_price: ?string, quarry_price: ?string}
     */
    public function defaults(ShipmentKind $kind, ?string $quarryId, ?string $materialId, ?string $addressId, CarbonInterface $on): array
    {
        return [
            'sale_price' => $this->price(TransportPriceKind::Sale, null, $addressId, $materialId, $on),
            'carrier_price' => $this->price(TransportPriceKind::Freight, $quarryId, $addressId, null, $on),
            'quarry_price' => $kind === ShipmentKind::MaterialSale
                ? $this->price(TransportPriceKind::Quarry, $quarryId, null, $materialId, $on)
                : null,
        ];
    }

    private function price(TransportPriceKind $kind, ?string $quarryId, ?string $addressId, ?string $materialId, CarbonInterface $on): ?string
    {
        $keys = match ($kind) {
            TransportPriceKind::Quarry => ['quarry_id' => $quarryId, 'material_id' => $materialId],
            TransportPriceKind::Sale => ['delivery_address_id' => $addressId, 'material_id' => $materialId],
            TransportPriceKind::Freight => ['quarry_id' => $quarryId, 'delivery_address_id' => $addressId],
        };

        if (in_array(null, $keys, true)) {
            return null;
        }

        return TransportPrice::query()
            ->where('kind', $kind)
            ->where($keys)
            ->whereDate('valid_from', '<=', $on->toDateString())
            ->orderByDesc('valid_from')
            ->value('price');
    }
}
