<?php

namespace App\Services\Ledger;

use App\Models\FuelReceipt;

/**
 * Fuel taken for a vehicle is debited to that vehicle's owner. The fuel firm is not credited here:
 * paying the firm is a separate petrol invoice, so the same litres are not counted twice.
 */
class FuelReceiptLedger
{
    public function __construct(private Ledger $ledger) {}

    public function sync(FuelReceipt $receipt): void
    {
        $receipt->loadMissing('fuelStation');

        $this->ledger->sync($receipt, [
            LedgerLine::debit(
                $receipt->party_id,
                $receipt->issued_on,
                $receipt->total(),
                sprintf(
                    'Yakıt fişi%s · %s · %s lt',
                    $receipt->ticket_no ? ' '.$receipt->ticket_no : '',
                    $receipt->fuelStation?->name,
                    FuelReceipt::formatQuantity($receipt->quantity),
                ),
                $receipt->vehicle_id,
            ),
        ]);
    }

    public function void(FuelReceipt $receipt): void
    {
        $this->ledger->void($receipt);
    }
}
