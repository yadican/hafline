<?php

namespace App\Services\Ledger;

use App\Models\Haul;

/**
 * A haul earns its price for the vehicle owner of the day and its dump fee for the dump operator.
 */
class HaulLedger
{
    public function __construct(private Ledger $ledger) {}

    public function sync(Haul $haul): void
    {
        $haul->loadMissing(['site', 'dumpSite']);

        $date = $haul->hauled_at;
        $description = sprintf(
            '%s · %s → %s',
            $haul->ticket_no ? 'Taşıma fişi '.$haul->ticket_no : 'Taşıma fişi',
            $haul->site?->name,
            $haul->dumpSite?->name,
        );

        $this->ledger->sync($haul, [
            LedgerLine::credit($haul->party_id, $date, $haul->price, $description, $haul->vehicle_id),
            LedgerLine::credit($haul->dump_party_id, $date, $haul->dump_price, $description, $haul->vehicle_id),
        ]);
    }

    public function void(Haul $haul): void
    {
        $this->ledger->void($haul);
    }
}
