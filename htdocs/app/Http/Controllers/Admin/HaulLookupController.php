<?php

namespace App\Http\Controllers\Admin;

use App\Models\Vehicle;
use App\Repositories\HaulLookupRepository;
use App\Services\HaulService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class HaulLookupController extends AdminController
{
    protected ?string $permissionAbility = 'view-haul';

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
                $vehicle->driver_name ? 'Şoför: '.$vehicle->driver_name : null,
            ])->filter()->implode(' · '),
        ]);

        return response()->json($vehicles);
    }

    /**
     * `current` is the route of the haul being edited, which stays selectable after it is closed.
     */
    public function dumpSites(Request $request, HaulService $hauls): JsonResponse
    {
        $dumpSites = collect($hauls->dumpSitesFor($this->uuid($request, 'site'), $this->uuid($request, 'current')))
            ->map(fn (string $name, string $id) => ['id' => $id, 'name' => $name])
            ->values();

        return response()->json($dumpSites);
    }

    /**
     * The price a haul would be saved with, so the entry screen can show it before submitting.
     */
    public function quote(Request $request, HaulService $hauls): JsonResponse
    {
        $vehicle = Vehicle::findByPlate((string) $request->query('plate', ''));
        $route = $hauls->findRoute(
            $this->uuid($request, 'site'),
            $this->uuid($request, 'dump_site'),
            $this->uuid($request, 'current'),
        );

        if (! $vehicle || ! $route) {
            return response()->json(['message' => 'Plaka veya güzergah bulunamadı.'], 404);
        }

        return response()->json($hauls->preview($vehicle->load('vehicleClass'), $route));
    }

    private function uuid(Request $request, string $key): ?string
    {
        $value = (string) $request->query($key, '');

        return Str::isUuid($value) ? $value : null;
    }
}
