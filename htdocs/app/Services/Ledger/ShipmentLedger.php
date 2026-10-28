<?php

namespace App\Services\Ledger;

use App\Models\Shipment;

/**
 * A shipment debits the customer (sale + VAT − withholding) and credits the carrier on the plate,
 * the quarry firm when we sold the material, and the commission agent.
 */
class ShipmentLedger
{
    public function __construct(private Ledger $ledger) {}

    public function sync(Shipment $shipment): void
    {
        $shipment->loadMissing(['quarry', 'deliveryAddress', 'material', 'vehicle']);

        $description = sprintf(
            'Sefer%s · %s → %s · %s %s %s',
            $shipment->waybill_no ? ' '.$shipment->waybill_no : '',
            $shipment->quarry?->name,
            $shipment->deliveryAddress?->name,
            Shipment::formatQuantity($shipment->quantity),
            $shipment->material?->unit->label(),
            $shipment->material?->name,
        );
        $withPlate = $description.' · '.$shipment->vehicle?->plate;

        $lines = [
            LedgerLine::debit($shipment->customer_party_id, $shipment->shipped_on, $shipment->customer_amount, $withPlate),
            LedgerLine::credit($shipment->party_id, $shipment->shipped_on, $shipment->carrier_amount, $description, $shipment->vehicle_id),
        ];

        if ($shipment->quarry_party_id) {
            $lines[] = LedgerLine::credit($shipment->quarry_party_id, $shipment->shipped_on, $shipment->quarry_amount, $withPlate);
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
