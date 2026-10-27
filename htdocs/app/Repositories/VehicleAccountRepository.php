<?php

namespace App\Repositories;

use App\Models\FuelReceipt;
use App\Models\Haul;
use App\Models\LedgerEntry;
use App\Models\MachineWork;
use App\Models\VehiclePayment;
use App\Models\VehicleReceivable;
use Illuminate\Support\Facades\DB;

/**
 * One party's account on one plate, built from the same documents the plate reports show.
 */
class VehicleAccountRepository
{
    /**
     * What the company owes the party on this plate (negative: the party owes the company),
     * optionally leaving out one transfer's own carry-over lines.
     */
    public function balance(string $vehicleId, string $partyId, ?string $exceptOwnershipId = null): float
    {
        $sum = fn ($query, string $column): float => (float) $query
            ->toBase()
            ->where('vehicle_id', $vehicleId)
            ->where('party_id', $partyId)
            ->whereNull('deleted_at')
            ->sum($column);

        $earned = $sum(Haul::query(), 'price')
            + $sum(MachineWork::query(), 'amount')
            + $sum(VehicleReceivable::query(), 'amount');

        $charged = $sum(FuelReceipt::query(), 'amount')
            + $sum(VehiclePayment::query(), 'amount')
            + (float) DB::table('vehicle_fines')
                ->where('vehicle_id', $vehicleId)
                ->where('party_id', $partyId)
                ->where('status', 'paid')
                ->whereNull('deleted_at')
                ->sum('amount');

        $carried = (float) LedgerEntry::query()
            ->toBase()
            ->where('source_type', 'vehicle_ownership')
            ->where('vehicle_id', $vehicleId)
            ->where('party_id', $partyId)
            ->when($exceptOwnershipId, fn ($query, string $id) => $query->where('source_id', '<>', $id))
            ->sum(DB::raw('credit - debit'));

        return round($earned - $charged + $carried, 2);
    }
}
