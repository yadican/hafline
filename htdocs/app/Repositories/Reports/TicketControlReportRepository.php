<?php

namespace App\Repositories\Reports;

use App\Enums\PartyRole;
use App\Models\FuelReceipt;
use App\Models\Haul;
use App\Models\TicketHandover;
use App\Models\Vehicle;
use App\Repositories\DumpSiteRepository;
use App\Repositories\PartyRepository;
use App\Repositories\SiteRepository;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TicketControlReportRepository
{
    public function __construct(
        private PartyRepository $parties,
        private SiteRepository $sites,
        private DumpSiteRepository $dumpSites,
    ) {}

    /**
     * @return array{sites: array<string, string>, dumpSites: array<string, string>, owners: array<string, string>}
     */
    public function formLookups(): array
    {
        return [
            'sites' => $this->sites->options(),
            'dumpSites' => $this->dumpSites->options(),
            'owners' => $this->parties->options(PartyRole::VehicleOwner),
        ];
    }

    public function findVehicleByPlate(string $plate): ?Vehicle
    {
        return Vehicle::findByPlate($plate);
    }

    /**
     * @param  array{
     *     from: Carbon,
     *     toExclusive: Carbon,
     *     site?: string|null,
     *     dump_site?: string|null,
     *     owner?: string|null,
     *     vehicle?: string|null,
     *     group: 'none'|'site'|'owner'|'dump_site'
     * }  $filters
     * @return Collection<int, object{group_key: ?string, group_label: ?string, total: string}>
     */
    public function handoverTotals(array $filters): Collection
    {
        $group = $filters['group'];
        $query = TicketHandover::query()
            ->toBase()
            ->whereNull('ticket_handovers.deleted_at')
            ->where('ticket_handovers.handed_on', '>=', $filters['from']->toDateString())
            ->where('ticket_handovers.handed_on', '<', $filters['toExclusive']->toDateString());

        if (filled($filters['site'] ?? null) || filled($filters['dump_site'] ?? null) || $group === 'site' || $group === 'dump_site') {
            $query->leftJoin('haul_routes', 'haul_routes.id', '=', 'ticket_handovers.route_id');
        }

        if (filled($filters['site'] ?? null)) {
            $query->where('haul_routes.site_id', $filters['site']);
        }

        if (filled($filters['dump_site'] ?? null)) {
            $query->where('haul_routes.dump_site_id', $filters['dump_site']);
        }

        if (filled($filters['vehicle'] ?? null)) {
            $query->where('ticket_handovers.vehicle_id', $filters['vehicle']);
        }

        if (filled($filters['owner'] ?? null) || $group === 'owner') {
            $query->leftJoin('vehicle_ownerships as vo', function ($join): void {
                $join->on('vo.vehicle_id', '=', 'ticket_handovers.vehicle_id')
                    ->whereNull('vo.ended_on');
            });
        }

        if (filled($filters['owner'] ?? null)) {
            $query->where('vo.party_id', $filters['owner']);
        }

        return $this->groupedSum(
            $query,
            $group,
            'COALESCE(SUM(ticket_handovers.haul_count), 0)',
            siteJoin: 'haul_routes.site_id',
            dumpJoin: 'haul_routes.dump_site_id',
            ownerJoin: 'vo.party_id',
        );
    }

    /**
     * @param  array{
     *     from: Carbon,
     *     toExclusive: Carbon,
     *     site?: string|null,
     *     dump_site?: string|null,
     *     owner?: string|null,
     *     vehicle?: string|null,
     *     group: 'none'|'site'|'owner'|'dump_site'
     * }  $filters
     * @return Collection<int, object{group_key: ?string, group_label: ?string, total: string}>
     */
    public function haulTotals(array $filters): Collection
    {
        $group = $filters['group'];
        $query = Haul::query()
            ->toBase()
            ->whereNull('hauls.deleted_at')
            ->where('hauls.hauled_at', '>=', $filters['from'])
            ->where('hauls.hauled_at', '<', $filters['toExclusive']);

        if (filled($filters['site'] ?? null)) {
            $query->where('hauls.site_id', $filters['site']);
        }

        if (filled($filters['dump_site'] ?? null)) {
            $query->where('hauls.dump_site_id', $filters['dump_site']);
        }

        if (filled($filters['vehicle'] ?? null)) {
            $query->where('hauls.vehicle_id', $filters['vehicle']);
        }

        if (filled($filters['owner'] ?? null)) {
            $query->where('hauls.party_id', $filters['owner']);
        }

        return $this->groupedSum(
            $query,
            $group,
            'COUNT(*)',
            siteJoin: 'hauls.site_id',
            dumpJoin: 'hauls.dump_site_id',
            ownerJoin: 'hauls.party_id',
        );
    }

    /**
     * Fuel ticket counts. Dump-site filter/group does not apply (returns empty when grouping by dump).
     *
     * @param  array{
     *     from: Carbon,
     *     toExclusive: Carbon,
     *     site?: string|null,
     *     dump_site?: string|null,
     *     owner?: string|null,
     *     vehicle?: string|null,
     *     group: 'none'|'site'|'owner'|'dump_site'
     * }  $filters
     * @return Collection<int, object{group_key: ?string, group_label: ?string, total: string}>
     */
    public function fuelTotals(array $filters): Collection
    {
        if (($filters['group'] ?? 'none') === 'dump_site' || filled($filters['dump_site'] ?? null)) {
            return collect();
        }

        $group = $filters['group'];
        $query = FuelReceipt::query()
            ->toBase()
            ->whereNull('fuel_receipts.deleted_at')
            ->where('fuel_receipts.issued_on', '>=', $filters['from']->toDateString())
            ->where('fuel_receipts.issued_on', '<', $filters['toExclusive']->toDateString());

        if (filled($filters['site'] ?? null)) {
            $query->where('fuel_receipts.site_id', $filters['site']);
        }

        if (filled($filters['vehicle'] ?? null)) {
            $query->where('fuel_receipts.vehicle_id', $filters['vehicle']);
        }

        if (filled($filters['owner'] ?? null)) {
            $query->where('fuel_receipts.party_id', $filters['owner']);
        }

        return $this->groupedSum(
            $query,
            $group,
            'COALESCE(SUM(fuel_receipts.haul_ticket_count), 0)',
            siteJoin: 'fuel_receipts.site_id',
            dumpJoin: null,
            ownerJoin: 'fuel_receipts.party_id',
        );
    }

    /**
     * @param  Builder  $query
     * @return Collection<int, object{group_key: ?string, group_label: ?string, total: string}>
     */
    private function groupedSum(
        $query,
        string $group,
        string $sumExpr,
        string $siteJoin,
        ?string $dumpJoin,
        string $ownerJoin,
    ): Collection {
        if ($group === 'none') {
            $total = (clone $query)->selectRaw("{$sumExpr} as total")->value('total');

            return collect([(object) [
                'group_key' => null,
                'group_label' => null,
                'total' => (string) ((int) $total),
            ]]);
        }

        if ($group === 'site') {
            $query->leftJoin('sites', 'sites.id', '=', $siteJoin);

            return $query
                ->groupBy($siteJoin, 'sites.name')
                ->orderBy('sites.name')
                ->get([
                    DB::raw("{$siteJoin} as group_key"),
                    'sites.name as group_label',
                    DB::raw("{$sumExpr} as total"),
                ]);
        }

        if ($group === 'dump_site') {
            if ($dumpJoin === null) {
                return collect();
            }

            $query->leftJoin('dump_sites', 'dump_sites.id', '=', $dumpJoin);

            return $query
                ->groupBy($dumpJoin, 'dump_sites.name')
                ->orderBy('dump_sites.name')
                ->get([
                    DB::raw("{$dumpJoin} as group_key"),
                    'dump_sites.name as group_label',
                    DB::raw("{$sumExpr} as total"),
                ]);
        }

        // owner
        $query->leftJoin('parties', 'parties.id', '=', $ownerJoin);

        return $query
            ->groupBy($ownerJoin, 'parties.name')
            ->orderBy('parties.name')
            ->get([
                DB::raw("{$ownerJoin} as group_key"),
                'parties.name as group_label',
                DB::raw("{$sumExpr} as total"),
            ]);
    }
}
