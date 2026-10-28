<?php

namespace App\Repositories;

use App\Models\Party;
use App\Models\Shipment;
use App\Models\Vehicle;
use App\Support\Sort;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\LazyCollection;

class ShipmentRepository extends Repository
{
    public const RECENT_DAYS = 30;

    public function __construct(Shipment $model)
    {
        parent::__construct($model);
    }

    /**
     * @param  array<string, string>  $filters
     */
    public function search(array $filters, ?Sort $sort, int $perPage): LengthAwarePaginator
    {
        $query = $this->filtered($filters)
            ->with(['vehicle', 'party', 'quarry', 'material', 'deliveryAddress', 'customer']);

        return $this->applySort($query, $sort)
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param  array<string, string>  $filters
     * @return array{count: int, quantity: float, sale: float, customer: float, carrier: float, quarry: float, commission: float, profit: float}
     */
    public function totals(array $filters): array
    {
        $row = $this->filtered($filters)
            ->toBase()
            ->selectRaw('count(*) as count, COALESCE(SUM(quantity), 0) AS quantity, COALESCE(SUM(sale_amount), 0) AS sale, COALESCE(SUM(customer_amount), 0) AS customer,
                COALESCE(SUM(carrier_amount), 0) AS carrier, COALESCE(SUM(quarry_amount), 0) AS quarry, COALESCE(SUM(commission_amount), 0) AS commission')
            ->first();

        return [
            'count' => (int) $row->count,
            'quantity' => (float) $row->quantity,
            'sale' => (float) $row->sale,
            'customer' => (float) $row->customer,
            'carrier' => (float) $row->carrier,
            'quarry' => (float) $row->quarry,
            'commission' => (float) $row->commission,
            'profit' => round((float) $row->sale - (float) $row->carrier - (float) $row->quarry - (float) $row->commission, 2),
        ];
    }

    /**
     * @return LazyCollection<int, Shipment>
     */
    public function forVehicleSince(string $vehicleId, CarbonInterface $from): LazyCollection
    {
        return $this->query()
            ->where('vehicle_id', $vehicleId)
            ->whereDate('shipped_on', '>=', $from->toDateString())
            ->orderBy('shipped_on')
            ->lazyById();
    }

    protected function defaultSort(Builder $query): void
    {
        $query->orderByDesc('shipped_on')->latest();
    }

    protected function sortColumns(): array
    {
        return [
            'shipped_on' => 'shipped_on',
            'quantity' => 'quantity',
            'customer_amount' => 'customer_amount',
            'plate' => fn (Builder $query, string $direction) => $query->orderBy(
                Vehicle::withTrashed()->select('plate')->whereColumn('vehicles.id', 'shipments.vehicle_id'),
                $direction,
            ),
            'customer' => fn (Builder $query, string $direction) => $query->orderBy(
                Party::withTrashed()->select('name')->whereColumn('parties.id', 'shipments.customer_party_id'),
                $direction,
            ),
        ];
    }

    /**
     * Without filters only the recent days are listed.
     *
     * @param  array<string, string>  $filters
     */
    private function filtered(array $filters): Builder
    {
        $query = $this->query();

        if ($filters === []) {
            return $query->whereDate('shipped_on', '>=', today()->subDays(self::RECENT_DAYS)->toDateString());
        }

        return $query
            ->when($filters['kind'] ?? null, fn (Builder $query, string $kind) => $query->where('kind', $kind))
            ->when($filters['customer'] ?? null, fn (Builder $query, string $party) => $query->where('customer_party_id', $party))
            ->when($filters['carrier'] ?? null, fn (Builder $query, string $party) => $query->where('party_id', $party))
            ->when($filters['quarry'] ?? null, fn (Builder $query, string $quarry) => $query->where('quarry_id', $quarry))
            ->when($filters['material'] ?? null, fn (Builder $query, string $material) => $query->where('material_id', $material))
            ->when($filters['from'] ?? null, fn (Builder $query, string $from) => $query->whereDate('shipped_on', '>=', $from))
            ->when($filters['to'] ?? null, fn (Builder $query, string $to) => $query->whereDate('shipped_on', '<=', $to))
            ->when($filters['waybill'] ?? null, fn (Builder $query, string $waybill) => $query->where('waybill_no', $waybill))
            ->when($filters['plate'] ?? null, function (Builder $query, string $plate): void {
                $like = $this->likePattern(Vehicle::normalizePlate($plate));

                $query->whereHas('vehicle', fn (Builder $vehicle) => $vehicle->whereLike('plate_key', $like));
            });
    }
}
