<?php

namespace App\Repositories;

use App\Models\FuelReceipt;
use App\Models\Site;
use App\Models\Vehicle;
use App\Support\Sort;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\LazyCollection;

class FuelReceiptRepository extends Repository
{
    public const RECENT_DAYS = 5;

    public function __construct(
        FuelReceipt $model,
        private FuelStationRepository $stations,
    ) {
        parent::__construct($model);
    }

    /**
     * @param  array<string, string>  $filters
     */
    public function search(array $filters, ?Sort $sort, int $perPage): LengthAwarePaginator
    {
        $query = $this->filtered($filters)
            ->with(['vehicle', 'party', 'fuelStation', 'site', 'creator']);

        return $this->applySort($query, $sort)
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param  array<string, string>  $filters
     * @return array{quantity: float, amount: float}
     */
    public function totals(array $filters): array
    {
        $row = $this->filtered($filters)
            ->toBase()
            ->selectRaw('COALESCE(SUM(quantity), 0) AS quantity, COALESCE(SUM(amount), 0) AS amount')
            ->first();

        return [
            'quantity' => (float) $row->quantity,
            'amount' => (float) $row->amount,
        ];
    }

    public function ticketTaken(string $ticketNo): bool
    {
        return $this->query()->where('ticket_no', $ticketNo)->exists();
    }

    /**
     * @return LazyCollection<int, FuelReceipt>
     */
    public function forVehicleSince(string $vehicleId, CarbonInterface $from): LazyCollection
    {
        return $this->query()
            ->where('vehicle_id', $vehicleId)
            ->whereDate('issued_on', '>=', $from->toDateString())
            ->orderBy('issued_on')
            ->lazyById();
    }

    /**
     * Active stations, plus one already chosen on a receipt.
     *
     * @return array<string, string>
     */
    public function stationOptions(?string $include = null): array
    {
        return $this->stations->options(activeOnly: true, include: $include);
    }

    protected function defaultSort(Builder $query): void
    {
        $query->orderByDesc('issued_on')->orderByDesc('created_at');
    }

    protected function sortColumns(): array
    {
        return [
            'ticket' => 'ticket_no',
            'plate' => fn (Builder $query, string $direction) => $query->orderBy(
                Vehicle::withTrashed()->select('plate_key')->whereColumn('vehicles.id', 'fuel_receipts.vehicle_id'),
                $direction,
            ),
            'station' => fn (Builder $query, string $direction) => $query->orderBy(
                FuelStation::withTrashed()->select('name')->whereColumn('fuel_stations.id', 'fuel_receipts.fuel_station_id'),
                $direction,
            ),
            'site' => fn (Builder $query, string $direction) => $query->orderBy(
                Site::withTrashed()->select('name')->whereColumn('sites.id', 'fuel_receipts.site_id'),
                $direction,
            ),
            'quantity' => 'quantity',
            'amount' => 'amount',
            'issued_on' => 'issued_on',
            'created_at' => 'created_at',
        ];
    }

    /**
     * @param  array<string, string>  $filters
     * @return Builder<FuelReceipt>
     */
    private function filtered(array $filters): Builder
    {
        $query = $this->query();

        if ($filters === []) {
            return $query->where('created_at', '>=', now()->subDays(self::RECENT_DAYS)->startOfDay());
        }

        return $query
            ->when($filters['site'] ?? null, fn (Builder $query, string $site) => $query->where('site_id', $site))
            ->when($filters['station'] ?? null, fn (Builder $query, string $station) => $query->where('fuel_station_id', $station))
            ->when($filters['owner'] ?? null, fn (Builder $query, string $owner) => $query->where('party_id', $owner))
            ->when($filters['shift'] ?? null, fn (Builder $query, string $shift) => $query->where('shift', $shift))
            ->when($filters['creator'] ?? null, fn (Builder $query, string $creator) => $query->where('created_by', $creator))
            ->when($filters['issued_from'] ?? null, fn (Builder $query, string $from) => $query->whereDate('issued_on', '>=', $from))
            ->when($filters['issued_to'] ?? null, fn (Builder $query, string $to) => $query->whereDate('issued_on', '<=', $to))
            ->when($filters['plate'] ?? null, function (Builder $query, string $plate): void {
                $like = $this->likePattern(Vehicle::normalizePlate($plate));

                $query->whereHas('vehicle', fn (Builder $vehicle) => $vehicle->whereLike('plate_key', $like));
            })
            ->when($filters['ticket'] ?? null, function (Builder $query, string $ticket): void {
                str_contains($ticket, '%')
                    ? $query->whereLike('ticket_no', $ticket)
                    : $query->where('ticket_no', $ticket);
            });
    }
}
