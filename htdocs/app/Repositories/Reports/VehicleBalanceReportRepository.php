<?php

namespace App\Repositories\Reports;

use App\Models\Vehicle;
use App\Repositories\FuelStationRepository;
use App\Repositories\SiteRepository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class VehicleBalanceReportRepository
{
    public function __construct(
        private SiteRepository $sites,
        private FuelStationRepository $stations,
    ) {}

    /**
     * @return array<string, string>
     */
    public function sites(): array
    {
        return $this->sites->options();
    }

    /**
     * @return array<string, string>
     */
    public function fuelStations(): array
    {
        return $this->stations->options();
    }

    /**
     * Per-vehicle credit/debit totals in a half-open date window [from, toExclusive).
     * Null bounds mean no date filter (all time).
     *
     * @return Collection<int, object{
     *     vehicle_id: string,
     *     plate: string,
     *     credit_hauls: string,
     *     credit_receivables: string,
     *     debit_payments: string,
     *     debit_fuel: string,
     *     debit_fines: string
     * }>
     */
    public function vehicleTotals(
        ?Carbon $from,
        ?Carbon $toExclusive,
        ?string $siteId = null,
        ?string $fuelStationId = null,
    ): Collection {
        $haulFrom = $from?->copy();
        $haulTo = $toExclusive?->copy();
        $dateFrom = $from?->toDateString();
        $dateTo = $toExclusive?->toDateString();

        $hauls = DB::table('hauls')
            ->select('vehicle_id', DB::raw('COALESCE(SUM(price), 0) as total'))
            ->whereNull('deleted_at')
            ->when($haulFrom, fn ($q) => $q->where('hauled_at', '>=', $haulFrom))
            ->when($haulTo, fn ($q) => $q->where('hauled_at', '<', $haulTo))
            ->when($siteId, fn ($q) => $q->where('site_id', $siteId))
            ->groupBy('vehicle_id');

        // Site filters hauls only; fuel station filters fuel only (legacy card).
        $fuel = DB::table('fuel_receipts')
            ->select('vehicle_id', DB::raw('COALESCE(SUM(amount), 0) as total'))
            ->whereNull('deleted_at')
            ->when($dateFrom, fn ($q) => $q->where('issued_on', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->where('issued_on', '<', $dateTo))
            ->when($fuelStationId, fn ($q) => $q->where('fuel_station_id', $fuelStationId))
            ->groupBy('vehicle_id');

        $payments = DB::table('vehicle_payments')
            ->select('vehicle_id', DB::raw('COALESCE(SUM(amount), 0) as total'))
            ->whereNull('deleted_at')
            ->when($dateFrom, fn ($q) => $q->where('occurred_on', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->where('occurred_on', '<', $dateTo))
            ->groupBy('vehicle_id');

        $receivables = DB::table('vehicle_receivables')
            ->select('vehicle_id', DB::raw('COALESCE(SUM(amount), 0) as total'))
            ->whereNull('deleted_at')
            ->when($dateFrom, fn ($q) => $q->where('occurred_on', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->where('occurred_on', '<', $dateTo))
            ->groupBy('vehicle_id');

        $fines = DB::table('vehicle_fines')
            ->select('vehicle_id', DB::raw('COALESCE(SUM(amount), 0) as total'))
            ->whereNull('deleted_at')
            ->where('status', 'paid')
            ->when($dateFrom, fn ($q) => $q->where('paid_on', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->where('paid_on', '<', $dateTo))
            ->groupBy('vehicle_id');

        return Vehicle::query()
            ->withTrashed()
            ->toBase()
            ->leftJoinSub($hauls, 'hauls_sum', 'hauls_sum.vehicle_id', '=', 'vehicles.id')
            ->leftJoinSub($fuel, 'fuel_sum', 'fuel_sum.vehicle_id', '=', 'vehicles.id')
            ->leftJoinSub($payments, 'payments_sum', 'payments_sum.vehicle_id', '=', 'vehicles.id')
            ->leftJoinSub($receivables, 'receivables_sum', 'receivables_sum.vehicle_id', '=', 'vehicles.id')
            ->leftJoinSub($fines, 'fines_sum', 'fines_sum.vehicle_id', '=', 'vehicles.id')
            ->select([
                'vehicles.id as vehicle_id',
                'vehicles.plate',
                DB::raw('COALESCE(hauls_sum.total, 0) as credit_hauls'),
                DB::raw('COALESCE(receivables_sum.total, 0) as credit_receivables'),
                DB::raw('COALESCE(payments_sum.total, 0) as debit_payments'),
                DB::raw('COALESCE(fuel_sum.total, 0) as debit_fuel'),
                DB::raw('COALESCE(fines_sum.total, 0) as debit_fines'),
            ])
            ->where(function ($query) use ($siteId, $fuelStationId): void {
                // Required filters must match real activity; Elden/ödeme alone must not fill the list.
                if (filled($siteId) && filled($fuelStationId)) {
                    $query->whereRaw('COALESCE(hauls_sum.total, 0) > 0')
                        ->whereRaw('COALESCE(fuel_sum.total, 0) > 0');

                    return;
                }

                if (filled($fuelStationId)) {
                    $query->whereRaw('COALESCE(fuel_sum.total, 0) > 0');

                    return;
                }

                if (filled($siteId)) {
                    $query->whereRaw('COALESCE(hauls_sum.total, 0) > 0');

                    return;
                }

                $query->whereRaw('(COALESCE(hauls_sum.total, 0) + COALESCE(receivables_sum.total, 0) + COALESCE(payments_sum.total, 0) + COALESCE(fuel_sum.total, 0) + COALESCE(fines_sum.total, 0)) <> 0');
            })
            ->orderBy('vehicles.plate')
            ->get();
    }
}
