<?php

namespace App\Http\Controllers\Admin;

use App\Models\Party;
use App\Models\Vehicle;
use App\Repositories\VehicleAccountRepository;
use App\Services\PartyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Throwable;

class CariEntryLookupController extends AdminController
{
    protected ?string $permissionAbility = 'view-cari-entry';

    public function __construct(
        private PartyService $parties,
        private VehicleAccountRepository $accounts,
    ) {}

    /**
     * Current ledger totals for the selected company, shown beside the new entry.
     */
    public function party(Party $party): JsonResponse
    {
        $totals = $this->parties->totals($party);

        return response()->json([
            'name' => $party->name,
            'debit' => $totals['debit'],
            'credit' => $totals['credit'],
            'balance' => $totals['balance'],
        ]);
    }

    /**
     * The owner on the entry date: their whole cari and their balance on this plate (debit − credit).
     */
    public function plate(Request $request): JsonResponse
    {
        $vehicle = Vehicle::findByPlate((string) $request->query('plate', ''));

        try {
            $date = Carbon::parse((string) $request->query('date', ''))->startOfDay();
        } catch (Throwable) {
            $date = null;
        }

        if (! $vehicle || ! $date) {
            return response()->json(['message' => 'Kayıtlı bir plaka girin.'], 404);
        }

        $owner = $vehicle->ownershipOn($date)?->party;

        if (! $owner) {
            return response()->json(['message' => 'Bu tarihte aracın kayıtlı sahibi yok.'], 404);
        }

        $totals = $this->parties->totals($owner);

        return response()->json([
            'plate' => $vehicle->plate,
            'name' => $owner->name,
            'debit' => $totals['debit'],
            'credit' => $totals['credit'],
            'balance' => $totals['balance'],
            'plate_balance' => -$this->accounts->balance($vehicle->id, $owner->id),
        ]);
    }
}
