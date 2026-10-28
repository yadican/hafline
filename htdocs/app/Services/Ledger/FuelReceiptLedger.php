<?php

namespace App\Services\Ledger;

use App\Models\FuelReceipt;

/**
 * Fuel given to a vehicle is debited to its owner at the sale price. At a station the station's
 * firm is credited at the purchase price on the same receipt; fuel from our own tank was already
 * credited to its supplier when the tank was filled.
 */
class FuelReceiptLedger
{
    public function __construct(private Ledger $ledger) {}

    public function sync(FuelReceipt $receipt): void
    {
        $receipt->loadMissing(['fuelStation', 'vehicle']);

        $description = sprintf(
            'Yakıt fişi%s · %s · %s lt',
            $receipt->ticket_no ? ' '.$receipt->ticket_no : '',
            $receipt->fuelStation?->name,
            FuelReceipt::formatQuantity($receipt->quantity),
        );

        $lines = [
            LedgerLine::debit($receipt->party_id, $receipt->issued_on, $receipt->total(), $description, $receipt->vehicle_id),
        ];

        if ($receipt->fuel_party_id) {
            $lines[] = LedgerLine::credit(
                $receipt->fuel_party_id,
                $receipt->issued_on,
                $receipt->purchaseTotal(),
                $description.' · '.$receipt->vehicle?->plate,
            );
        }

        $this->ledger->sync($receipt, $lines);
    }

    public function void(FuelReceipt $receipt): void
    {
        $this->ledger->void($receipt);
    }
}
