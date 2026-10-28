<?php

namespace App\Services\Ledger;

use App\Models\Shipment;

/**
 * A trip debits the customer (base + VAT − withholding) and credits the carrier on the plate
 * (base + VAT − withholding), the driver's trip fee on the plate, and the referrer.
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

        if ($shipment->driver_party_id) {
            $lines[] = LedgerLine::credit($shipment->driver_party_id, $shipment->shipped_on, $shipment->driver_amount, 'Sefer parası · '.$withPlate, $shipment->vehicle_id);
        }

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
