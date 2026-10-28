<?php

namespace App\Repositories;

use App\Models\DeliveryAddress;
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
            ->with(['vehicle', 'party', 'deliveryAddress', 'customer']);

        return $this->applySort($query, $sort)
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param  array<string, string>  $filters
     * @return array{count: int, net_kg: int, customer: float, carrier: float, profit: float}
     */
    public function totals(array $filters): array
    {
        $row = $this->filtered($filters)
            ->toBase()
            ->selectRaw('count(*) as count, COALESCE(SUM(net_kg), 0) AS net_kg, COALESCE(SUM(sale_amount), 0) AS sale, COALESCE(SUM(customer_amount), 0) AS customer,
                COALESCE(SUM(carrier_amount), 0) AS carrier_base, COALESCE(SUM(carrier_net_amount), 0) AS carrier, COALESCE(SUM(commission_amount), 0) AS commission')
            ->first();

        return [
            'count' => (int) $row->count,
            'net_kg' => (int) $row->net_kg,
            'customer' => (float) $row->customer,
            'carrier' => (float) $row->carrier,
            'profit' => round((float) $row->sale - (float) $row->carrier_base - (float) $row->commission, 2),
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

    /**
     * Address names already used for a customer, for the trip form's suggestions.
     *
     * @return list<string>
     */
    public function addressNames(string $customerId): array
    {
        return DeliveryAddress::query()->where('party_id', $customerId)->orderBy('name')->pluck('name')->all();
    }

    protected function defaultSort(Builder $query): void
    {
        $query->orderByDesc('shipped_on')->latest();
    }

    protected function sortColumns(): array
    {
        return [
            'shipped_on' => 'shipped_on',
            'net_kg' => 'net_kg',
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
            ->when($filters['customer'] ?? null, fn (Builder $query, string $party) => $query->where('customer_party_id', $party))
            ->when($filters['carrier'] ?? null, fn (Builder $query, string $party) => $query->where('party_id', $party))
            ->when($filters['from'] ?? null, fn (Builder $query, string $from) => $query->whereDate('shipped_on', '>=', $from))
            ->when($filters['to'] ?? null, fn (Builder $query, string $to) => $query->whereDate('shipped_on', '<=', $to))
            ->when($filters['waybill'] ?? null, fn (Builder $query, string $waybill) => $query->where('waybill_no', $waybill))
            ->when($filters['loading'] ?? null, fn (Builder $query, string $place) => $query->whereLike('loading_place', $this->likePattern($place)))
            ->when($filters['plate'] ?? null, function (Builder $query, string $plate): void {
                $like = $this->likePattern(Vehicle::normalizePlate($plate));

                $query->whereHas('vehicle', fn (Builder $vehicle) => $vehicle->whereLike('plate_key', $like));
            });
    }
}
