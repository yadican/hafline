<?php

namespace App\Repositories;

use App\Models\DumpSite;
use App\Models\Haul;
use App\Models\Site;
use App\Models\Vehicle;
use App\Support\Sort;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\LazyCollection;

class HaulRepository extends Repository
{
    public const RECENT_DAYS = 5;

    public function __construct(Haul $model)
    {
        parent::__construct($model);
    }

    /**
     * @param  array<string, string>  $filters
     */
    public function search(array $filters, ?Sort $sort, int $perPage): LengthAwarePaginator
    {
        $query = $this->filtered($filters)
            ->with(['vehicle.vehicleClass', 'site', 'dumpSite', 'creator']);

        return $this->applySort($query, $sort)
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param  array<string, string>  $filters
     * @return array{price: float, tonnage: int}
     */
    public function totals(array $filters): array
    {
        $row = $this->filtered($filters)
            ->toBase()
            ->selectRaw('COALESCE(SUM(price), 0) AS price, COALESCE(SUM(tonnage), 0) AS tonnage')
            ->first();

        return [
            'price' => (float) $row->price,
            'tonnage' => (int) $row->tonnage,
        ];
    }

    protected function sortColumns(): array
    {
        return [
            'ticket' => 'ticket_no',
            'plate' => fn (Builder $query, string $direction) => $query->orderBy(
                Vehicle::withTrashed()->select('plate_key')->whereColumn('vehicles.id', 'hauls.vehicle_id'),
                $direction,
            ),
            'site' => fn (Builder $query, string $direction) => $query->orderBy(
                Site::withTrashed()->select('name')->whereColumn('sites.id', 'hauls.site_id'),
                $direction,
            ),
            'dump_site' => fn (Builder $query, string $direction) => $query->orderBy(
                DumpSite::withTrashed()->select('name')->whereColumn('dump_sites.id', 'hauls.dump_site_id'),
                $direction,
            ),
            'price' => 'price',
            'tonnage' => 'tonnage',
            'hauled_at' => 'hauled_at',
            'created_at' => 'created_at',
        ];
    }

    public function ticketTaken(string $ticketNo): bool
    {
        return $this->query()->where('ticket_no', $ticketNo)->exists();
    }

    /**
     * @return LazyCollection<int, Haul>
     */
    public function forVehicleSince(string $vehicleId, CarbonInterface $from): LazyCollection
    {
        return $this->query()
            ->where('vehicle_id', $vehicleId)
            ->where('hauled_at', '>=', $from->copy()->startOfDay())
            ->orderBy('hauled_at')
            ->lazyById();
    }

    /**
     * Without filters only the last few days are listed to keep data entry fast.
     *
     * @param  array<string, string>  $filters
     * @return Builder<Haul>
     */
    private function filtered(array $filters): Builder
    {
        $query = $this->query();

        if ($filters === []) {
            return $query->where('created_at', '>=', now()->subDays(self::RECENT_DAYS)->startOfDay());
        }

        return $query
            ->when($filters['site'] ?? null, fn (Builder $query, string $site) => $query->where('site_id', $site))
            ->when($filters['dump_site'] ?? null, fn (Builder $query, string $dumpSite) => $query->where('dump_site_id', $dumpSite))
            ->when($filters['hauled_from'] ?? null, fn (Builder $query, string $from) => $query->where('hauled_at', '>=', Carbon::parse($from)))
            ->when($filters['hauled_to'] ?? null, fn (Builder $query, string $to) => $query->where('hauled_at', '<=', Carbon::parse($to)))
            ->when($filters['entered_from'] ?? null, fn (Builder $query, string $from) => $query->where('created_at', '>=', Carbon::parse($from)))
            ->when($filters['entered_to'] ?? null, fn (Builder $query, string $to) => $query->where('created_at', '<=', Carbon::parse($to)))
            ->when($filters['owner'] ?? null, fn (Builder $query, string $owner) => $query->where('party_id', $owner))
            ->when($filters['class'] ?? null, fn (Builder $query, string $class) => $query->whereHas('vehicle', fn (Builder $vehicle) => $vehicle->where('vehicle_class_id', $class)))
            ->when($filters['plate'] ?? null, function (Builder $query, string $plate): void {
                $like = '%'.addcslashes(Vehicle::normalizePlate($plate), '%_\\').'%';

                $query->whereHas('vehicle', fn (Builder $vehicle) => $vehicle->whereLike('plate_key', $like));
            })
            ->when($filters['shift'] ?? null, fn (Builder $query, string $shift) => $query->where('shift', $shift))
            ->when($filters['ticket'] ?? null, function (Builder $query, string $ticket): void {
                str_contains($ticket, '%')
                    ? $query->whereLike('ticket_no', $ticket)
                    : $query->where('ticket_no', $ticket);
            })
            ->when($filters['creator'] ?? null, fn (Builder $query, string $creator) => $query->where('created_by', $creator));
    }
}
