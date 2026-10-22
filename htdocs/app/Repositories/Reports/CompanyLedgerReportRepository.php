<?php

namespace App\Repositories\Reports;

use App\Enums\PartyRole;
use App\Models\Party;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CompanyLedgerReportRepository
{
    /**
     * @return array<string, string>
     */
    public function partiesForRole(PartyRole $role): array
    {
        return Party::query()
            ->withRole($role)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * Opening balances keyed by party_id for many parties.
     *
     * @param  list<string>  $partyIds
     * @return array<string, float>
     */
    public function openingBalances(PartyRole $role, array $partyIds, Carbon $from): array
    {
        if ($partyIds === []) {
            return [];
        }

        return DB::table('company_transactions')
            ->whereNull('deleted_at')
            ->where('role', $role->value)
            ->whereIn('party_id', $partyIds)
            ->where('occurred_on', '<', $from->toDateString())
            ->groupBy('party_id')
            ->selectRaw('party_id, COALESCE(SUM(debit - credit), 0) as total')
            ->pluck('total', 'party_id')
            ->map(fn ($total) => round((float) $total, 2))
            ->all();
    }

    /**
     * Party ids that have an opening balance or period movement for the role.
     *
     * @return LengthAwarePaginator<int, object{party_id: string, name: string}>|Collection<int, object{party_id: string, name: string}>
     */
    public function partiesWithActivity(
        PartyRole $role,
        Carbon $from,
        Carbon $toExclusive,
        ?string $partyId = null,
        bool $paginate = false,
        int $perPage = 20,
    ): LengthAwarePaginator|Collection {
        $dateFrom = $from->toDateString();
        $dateTo = $toExclusive->toDateString();

        $query = DB::table('company_transactions as ct')
            ->join('parties as p', 'p.id', '=', 'ct.party_id')
            ->whereNull('ct.deleted_at')
            ->where('ct.role', $role->value)
            ->when($partyId, fn ($q) => $q->where('ct.party_id', $partyId))
            ->groupBy('p.id', 'p.name')
            ->select(['p.id as party_id', 'p.name'])
            ->havingRaw(
                '(COALESCE(SUM(CASE WHEN ct.occurred_on < ? THEN ct.debit - ct.credit ELSE 0 END), 0) <> 0
                OR COALESCE(SUM(CASE WHEN ct.occurred_on >= ? AND ct.occurred_on < ? THEN ct.debit + ct.credit ELSE 0 END), 0) <> 0)',
                [$dateFrom, $dateFrom, $dateTo],
            )
            ->orderBy('p.name');

        if ($paginate) {
            return $query->paginate($perPage)->withQueryString();
        }

        return $query->get();
    }

    /**
     * Period lines for the given parties + role, ordered for running balance.
     *
     * @param  list<string>  $partyIds
     * @return Collection<int, object{
     *     id: string,
     *     party_id: string,
     *     party_name: string,
     *     occurred_on: string,
     *     document_no: ?string,
     *     description: ?string,
     *     debit: string,
     *     credit: string,
     *     liters: ?string,
     *     unit_price: ?string,
     *     fuel_station_name: ?string,
     *     plate: ?string,
     *     owner_name: ?string
     * }>
     */
    public function periodLines(PartyRole $role, array $partyIds, Carbon $from, Carbon $toExclusive): Collection
    {
        if ($partyIds === []) {
            return collect();
        }

        return DB::table('company_transactions as ct')
            ->join('parties as p', 'p.id', '=', 'ct.party_id')
            ->leftJoin('fuel_stations as fs', 'fs.id', '=', 'ct.fuel_station_id')
            ->leftJoin('vehicles as v', 'v.id', '=', 'ct.vehicle_id')
            ->leftJoin('parties as owner', 'owner.id', '=', 'ct.owner_party_id')
            ->whereNull('ct.deleted_at')
            ->where('ct.role', $role->value)
            ->whereIn('ct.party_id', $partyIds)
            ->where('ct.occurred_on', '>=', $from->toDateString())
            ->where('ct.occurred_on', '<', $toExclusive->toDateString())
            ->orderBy('p.name')
            ->orderBy('ct.occurred_on')
            ->orderBy('ct.created_at')
            ->orderBy('ct.id')
            ->select([
                'ct.id',
                'ct.party_id',
                'p.name as party_name',
                'ct.occurred_on',
                'ct.document_no',
                'ct.description',
                'ct.debit',
                'ct.credit',
                'ct.liters',
                'ct.unit_price',
                'fs.name as fuel_station_name',
                'v.plate',
                'owner.name as owner_name',
            ])
            ->get();
    }
}
