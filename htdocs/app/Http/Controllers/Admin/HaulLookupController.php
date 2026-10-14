<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use App\Repositories\HaulLookupRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HaulLookupController extends Controller
{
    public function __construct(private HaulLookupRepository $lookups) {}

    public function dumpSites(Request $request): JsonResponse
    {
        $dumpSites = collect($this->lookups->dumpSitesForSite($request->string('site')->value() ?: null))
            ->map(fn (string $name, string $id) => ['id' => $id, 'name' => $name])
            ->values();

        return response()->json($dumpSites);
    }

    public function vehicles(Request $request): JsonResponse
    {
        $term = (string) $request->query('q', '');

        if (mb_strlen(Vehicle::normalizePlate($term)) < 2) {
            return response()->json([]);
        }

        $vehicles = $this->lookups->vehicles($term)->map(fn (Vehicle $vehicle) => [
            'plate' => $vehicle->plate,
            'owner' => $vehicle->owner?->full_name,
            'class' => $vehicle->vehicleClass?->name,
            'driver' => $vehicle->driver_name,
        ]);

        return response()->json($vehicles);
    }
}
