<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ShipmentKind;
use App\Models\Vehicle;
use App\Repositories\HaulLookupRepository;
use App\Services\ShipmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ShipmentLookupController extends AdminController
{
    protected ?string $permissionAbility = 'view-shipment';

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
     * Default unit prices from the price lists, so the form can fill them in.
     */
    public function prices(Request $request, ShipmentService $shipments): JsonResponse
    {
        $validated = $request->validate([
            'kind' => ['required', 'string'],
            'quarry_id' => ['nullable', 'uuid'],
            'material_id' => ['nullable', 'uuid'],
            'delivery_address_id' => ['nullable', 'uuid'],
            'shipped_on' => ['nullable', 'date'],
        ]);

        return response()->json($shipments->defaultPrices(
            ShipmentKind::tryFrom($validated['kind']) ?? ShipmentKind::MaterialSale,
            $validated['quarry_id'] ?? null,
            $validated['material_id'] ?? null,
            $validated['delivery_address_id'] ?? null,
            Carbon::parse($validated['shipped_on'] ?? today()),
        ));
    }
}
