<?php

namespace App\Services\Ledger;

use App\Models\Shipment;

/**
 * A trip debits the customer (base + VAT − withholding) and credits the carrier on the plate twice:
 * the trip (base + VAT − withholding) and, as its own item, the driver's fee. The referrer is credited too.
 */
class ShipmentLedger
{
    public function __construct(private Ledger $ledger) {}

    public function sync(Shipment $shipment): void
    {
        $shipment->loadMissing(['deliveryAddress', 'vehicle']);

        $description = sprintf(
            'Sefer%s · %s → %s · %s kg',
            $shipment->waybill_no ? ' '.$shipment->waybill_no : '',
            $shipment->loading_place,
            $shipment->deliveryAddress?->name ?? 'adres yok',
            Shipment::formatKg($shipment->net_kg),
        );
        $withPlate = $description.' · '.$shipment->vehicle?->plate;

        $lines = [
            LedgerLine::debit($shipment->customer_party_id, $shipment->shipped_on, $shipment->customer_amount, $withPlate),
            LedgerLine::credit($shipment->party_id, $shipment->shipped_on, $shipment->carrier_net_amount, $description, $shipment->vehicle_id),
        ];

        $lines[] = LedgerLine::credit(
            $shipment->party_id,
            $shipment->shipped_on,
            $shipment->driver_amount,
            sprintf('Şoför ücreti%s · %s', $shipment->driver_name ? ' · '.$shipment->driver_name : '', $withPlate),
            $shipment->vehicle_id,
        );

        if ($shipment->commission_party_id) {
            $lines[] = LedgerLine::credit($shipment->commission_party_id, $shipment->shipped_on, $shipment->commission_amount, $withPlate);
        }

        $this->ledger->sync($shipment, $lines);
    }

    public function void(Shipment $shipment): void
    {
        $this->ledger->void($shipment);
    }
}
