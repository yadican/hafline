<?php

namespace App\Services\Ledger;

use App\Models\VehicleOwnership;

/**
 * A transfer closes the previous owner's balance on the plate and opens it on the new owner, as a
 * pair of offsetting lines on the transfer date. No cash moves, so the lines carry no payment method.
 */
class OwnershipTransferLedger
{
    public function __construct(private Ledger $ledger) {}

    public function sync(VehicleOwnership $ownership): void
    {
        $ownership->loadMissing(['vehicle', 'party', 'carriedFrom']);

        $balance = (float) $ownership->carried_balance;
        $from = $ownership->carried_from_party_id;

        if ($from === null || $balance === 0.0) {
            $this->ledger->sync($ownership, []);

            return;
        }

        $amount = abs($balance);
        $plate = $ownership->vehicle->plate;
        $closing = sprintf('Devir kapanışı · %s → %s', $plate, $ownership->party->name);
        $opening = sprintf('Devralınan bakiye · %s ← %s', $plate, $ownership->carriedFrom->name);

        $this->ledger->sync($ownership, $balance > 0
            ? [
                LedgerLine::debit($from, $ownership->started_on, $amount, $closing, $ownership->vehicle_id),
                LedgerLine::credit($ownership->party_id, $ownership->started_on, $amount, $opening, $ownership->vehicle_id),
            ]
            : [
                LedgerLine::credit($from, $ownership->started_on, $amount, $closing, $ownership->vehicle_id),
                LedgerLine::debit($ownership->party_id, $ownership->started_on, $amount, $opening, $ownership->vehicle_id),
            ]);
    }

    public function void(VehicleOwnership $ownership): void
    {
        $this->ledger->void($ownership);
    }
}
