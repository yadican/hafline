<?php

namespace App\Repositories\Reports;

use App\Enums\PartyRole;
use App\Models\Party;
use App\Models\Vehicle;
use App\Repositories\PartyRepository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class VehicleCostReportRepository
{
    public function __construct(private PartyRepository $parties) {}

    /**
     * @return array<string, string>
     */
    public function ownerOptions(?string $include = null): array
    {
        return $this->parties->options(PartyRole::VehicleOwner, $include);
    }

    public function findOwner(string $partyId): ?Party
    {
        return Party::withTrashed()->find($partyId);
    }

    /**
     * Per-vehicle gelir / yakıt / piyasa for one owner snapshot in [from, toExclusive).
     *
     * @return Collection<int, object{
     *     vehicle_id: string,
     *     plate: string,
     *     gelir: string,
     *     yakit: string,
     *     piyasa: string
     * }>
     */
    public function vehicleTotals(string $ownerId, Carbon $from, Carbon $toExclusive): Collection
    {
        $hauls = DB::table('hauls')
            ->select('vehicle_id', DB::raw('COALESCE(SUM(price), 0) as total'))
            ->where('party_id', $ownerId)
            ->whereNull('deleted_at')
            ->where('hauled_at', '>=', $from)
            ->where('hauled_at', '<', $toExclusive)
            ->groupBy('vehicle_id');

        $fuel = DB::table('fuel_receipts')
            ->select('vehicle_id', DB::raw('COALESCE(SUM(amount), 0) as total'))
            ->where('party_id', $ownerId)
            ->whereNull('deleted_at')
            ->where('issued_on', '>=', $from->toDateString())
            ->where('issued_on', '<', $toExclusive->toDateString())
            ->groupBy('vehicle_id');

        // Legacy summed A+B amounts; each row is XOR debit/credit so credit+debit = amount.
        $market = DB::table('company_transactions')
            ->select('vehicle_id', DB::raw('COALESCE(SUM(credit + debit), 0) as total'))
            ->where('role', PartyRole::Market->value)
            ->where('owner_party_id', $ownerId)
            ->whereNotNull('vehicle_id')
            ->whereNull('deleted_at')
            ->where('occurred_on', '>=', $from->toDateString())
            ->where('occurred_on', '<', $toExclusive->toDateString())
            ->groupBy('vehicle_id');

        return Vehicle::query()
            ->withTrashed()
            ->toBase()
            ->leftJoinSub($hauls, 'hauls_sum', 'hauls_sum.vehicle_id', '=', 'vehicles.id')
            ->leftJoinSub($fuel, 'fuel_sum', 'fuel_sum.vehicle_id', '=', 'vehicles.id')
            ->leftJoinSub($market, 'market_sum', 'market_sum.vehicle_id', '=', 'vehicles.id')
            ->select([
                'vehicles.id as vehicle_id',
                'vehicles.plate',
                DB::raw('COALESCE(hauls_sum.total, 0) as gelir'),
                DB::raw('COALESCE(fuel_sum.total, 0) as yakit'),
                DB::raw('COALESCE(market_sum.total, 0) as piyasa'),
            ])
            ->whereRaw('(COALESCE(hauls_sum.total, 0) + COALESCE(fuel_sum.total, 0) + COALESCE(market_sum.total, 0)) <> 0')
            ->orderBy('vehicles.plate')
            ->get();
    }

    /**
     * Per-owner gelir / yakıt / piyasa in [from, toExclusive).
     *
     * @return Collection<int, object{
     *     owner_id: string,
     *     owner_name: string,
     *     gelir: string,
     *     yakit: string,
     *     piyasa: string
     * }>
     */
    public function ownerTotals(Carbon $from, Carbon $toExclusive): Collection
    {
        $hauls = DB::table('hauls')
            ->select('party_id', DB::raw('COALESCE(SUM(price), 0) as total'))
            ->whereNull('deleted_at')
            ->where('hauled_at', '>=', $from)
            ->where('hauled_at', '<', $toExclusive)
            ->groupBy('party_id');

        $fuel = DB::table('fuel_receipts')
            ->select('party_id', DB::raw('COALESCE(SUM(amount), 0) as total'))
            ->whereNull('deleted_at')
            ->where('issued_on', '>=', $from->toDateString())
            ->where('issued_on', '<', $toExclusive->toDateString())
            ->groupBy('party_id');

        $market = DB::table('company_transactions')
            ->select('owner_party_id as party_id', DB::raw('COALESCE(SUM(credit + debit), 0) as total'))
            ->where('role', PartyRole::Market->value)
            ->whereNotNull('owner_party_id')
            ->whereNotNull('vehicle_id')
            ->whereNull('deleted_at')
            ->where('occurred_on', '>=', $from->toDateString())
            ->where('occurred_on', '<', $toExclusive->toDateString())
            ->groupBy('owner_party_id');

        return Party::query()
            ->withTrashed()
            ->toBase()
            ->leftJoinSub($hauls, 'hauls_sum', 'hauls_sum.party_id', '=', 'parties.id')
            ->leftJoinSub($fuel, 'fuel_sum', 'fuel_sum.party_id', '=', 'parties.id')
            ->leftJoinSub($market, 'market_sum', 'market_sum.party_id', '=', 'parties.id')
            ->select([
                'parties.id as owner_id',
                'parties.name as owner_name',
                DB::raw('COALESCE(hauls_sum.total, 0) as gelir'),
                DB::raw('COALESCE(fuel_sum.total, 0) as yakit'),
                DB::raw('COALESCE(market_sum.total, 0) as piyasa'),
            ])
            ->whereRaw('(COALESCE(hauls_sum.total, 0) + COALESCE(fuel_sum.total, 0) + COALESCE(market_sum.total, 0)) <> 0')
            ->orderBy('parties.name')
            ->get();
    }
}
