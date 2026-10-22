<?php

namespace App\Http\Controllers\Admin;

use App\Models\Party;
use App\Models\Vehicle;
use App\Services\MachineWorkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Throwable;

class MachineWorkLookupController extends AdminController
{
    protected ?string $permissionAbility = 'view-machine-work';

    public function __construct(private MachineWorkService $works) {}

    public function machines(Request $request): JsonResponse
    {
        $term = (string) $request->query('q', '');

        if (mb_strlen(Vehicle::normalizePlate($term)) < 2) {
            return response()->json([]);
        }

        $machines = $this->works->machines($term)->map(fn (Vehicle $vehicle) => [
            'value' => $vehicle->plate,
            'meta' => collect([
                $vehicle->currentOwnership?->party?->name,
                $vehicle->vehicleClass?->name,
                $vehicle->currentRate ? number_format((float) $vehicle->currentRate->hourly_rate, 2, ',', '.').' TL/saat' : 'Saat ücreti yok',
            ])->filter()->implode(' · '),
        ]);

        return response()->json($machines);
    }

    /**
     * Whether the operator is salaried staff (nothing is credited) and the rate of their latest ticket.
     */
    public function operator(Request $request): JsonResponse
    {
        $operator = Party::query()->find((string) $request->query('id', ''));

        if (! $operator) {
            return response()->json(['message' => 'Operatör bulunamadı.'], 404);
        }

        $salaried = $this->works->isSalaried($operator);

        return response()->json([
            'salaried' => $salaried,
            'rate' => $salaried ? null : $this->works->lastOperatorRate($operator),
        ]);
    }

    /**
     * The rate and owner a ticket for this machine on this date would be saved with.
     */
    public function rate(Request $request): JsonResponse
    {
        $vehicle = Vehicle::findByPlate((string) $request->query('plate', ''));

        try {
            $date = Carbon::parse((string) $request->query('date', ''))->startOfDay();
        } catch (Throwable) {
            $date = null;
        }

        if (! $vehicle || ! $date) {
            return response()->json(['message' => 'Plaka veya tarih geçersiz.'], 404);
        }

        $rate = $vehicle->rateOn($date);

        return response()->json([
            'hourly' => $vehicle->isHourly(),
            'owner' => $vehicle->ownershipOn($date)?->party?->name,
            'rate' => $rate?->hourly_rate,
            'since' => $rate?->started_on->format('d.m.Y'),
        ]);
    }
}
