<?php

namespace App\Http\Controllers\Admin;

use App\Models\FuelStation;
use App\Models\Vehicle;
use App\Repositories\HaulLookupRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FuelReceiptLookupController extends AdminController
{
    protected ?string $permissionAbility = 'view-fuel-receipt';

    public function __construct(private HaulLookupRepository $lookups) {}

    public function vehicles(Request $request): JsonResponse
    {
        $term = (string) $request->query('q', '');

        if (mb_strlen(Vehicle::normalizePlate($term)) < 2) {
            return response()->json([]);
        }

        $vehicles = $this->lookups->vehicles($term)->map(fn (Vehicle $vehicle) => [
            'value' => $vehicle->plate,
            'meta' => collect([
                $vehicle->currentOwnership?->party?->name,
                $vehicle->vehicleClass?->name,
            ])->filter()->implode(' · '),
        ]);

        return response()->json($vehicles);
    }

    /**
     * The station's price and the sites it serves, so the form can fill them in.
     */
    public function station(FuelStation $fuelStation): JsonResponse
    {
        $fuelStation->load('sites');

        return response()->json([
            'party' => $fuelStation->party?->name,
            'tank' => $fuelStation->isTank(),
            'unit_price' => $fuelStation->unit_price,
            'purchase_price' => $fuelStation->purchase_price,
            'sites' => $fuelStation->sites->map(fn ($site) => ['id' => $site->id, 'name' => $site->name])->values(),
        ]);
    }
}
