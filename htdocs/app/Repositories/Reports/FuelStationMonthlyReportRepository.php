<?php

namespace App\Repositories\Reports;

use App\Models\FuelReceipt;
use App\Models\FuelStation;
use App\Models\VehicleClass;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FuelStationMonthlyReportRepository
{
    /**
     * @return array<string, string>
     */
    public function fuelStations(): array
    {
        return FuelStation::query()->orderBy('name')->pluck('name', 'id')->all();
    }

    /**
     * @return array<string, string>
     */
    public function vehicleClasses(): array
    {
        return VehicleClass::query()->orderBy('name')->pluck('name', 'id')->all();
    }

    public function findFuelStation(string $fuelStationId): ?FuelStation
    {
        return FuelStation::withTrashed()->find($fuelStationId);
    }

    /**
     * Liters per vehicle per issued_on day for one station in [from, toExclusive).
     *
     * @return Collection<int, object{vehicle_id: string, plate: string, issued_on: string, liters: string}>
     */
    public function dayLiters(string $fuelStationId, Carbon $from, Carbon $toExclusive, ?string $vehicleClassId = null): Collection
    {
        return FuelReceipt::query()
            ->toBase()
            ->join('vehicles', 'vehicles.id', '=', 'fuel_receipts.vehicle_id')
            ->where('fuel_receipts.fuel_station_id', $fuelStationId)
            ->where('fuel_receipts.issued_on', '>=', $from->toDateString())
            ->where('fuel_receipts.issued_on', '<', $toExclusive->toDateString())
            ->whereNull('fuel_receipts.deleted_at')
            ->when($vehicleClassId, fn ($query, string $class) => $query->where('vehicles.vehicle_class_id', $class))
            ->groupBy('fuel_receipts.vehicle_id', 'vehicles.plate', 'fuel_receipts.issued_on')
            ->orderBy('vehicles.plate')
            ->orderBy('fuel_receipts.issued_on')
            ->get([
                'fuel_receipts.vehicle_id',
                'vehicles.plate',
                'fuel_receipts.issued_on',
                DB::raw('SUM(fuel_receipts.quantity) as liters'),
            ]);
    }
}
