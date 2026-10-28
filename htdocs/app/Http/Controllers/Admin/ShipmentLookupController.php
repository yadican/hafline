<?php

namespace App\Http\Controllers\Admin;

use App\Models\Vehicle;
use App\Repositories\HaulLookupRepository;
use App\Services\ShipmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShipmentLookupController extends AdminController
{
    protected ?string $permissionAbility = 'view-shipment';

    /**
     * Plates matching the term with their owner, who is the trip's carrier.
     */
    public function vehicles(Request $request, HaulLookupRepository $lookups): JsonResponse
    {
        $term = (string) $request->query('q', '');

        if (mb_strlen(Vehicle::normalizePlate($term)) < 2) {
            return response()->json([]);
        }

        return response()->json($lookups->vehicles($term)->map(fn (Vehicle $vehicle) => [
            'value' => $vehicle->plate,
            'meta' => $vehicle->currentOwnership?->party?->name ?? '',
        ]));
    }

    /**
     * Delivery addresses already used for the customer.
     */
    public function addresses(Request $request, ShipmentService $shipments): JsonResponse
    {
        $validated = $request->validate(['party' => ['required', 'uuid']]);

        return response()->json($shipments->addressNames($validated['party']));
    }
}
