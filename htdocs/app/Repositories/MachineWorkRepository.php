<?php

namespace App\Repositories;

use App\Models\MachineWork;
use App\Models\Site;
use App\Models\Vehicle;
use App\Support\Sort;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\LazyCollection;

class MachineWorkRepository extends Repository
{
    public const RECENT_DAYS = 5;

    public function __construct(MachineWork $model)
    {
        parent::__construct($model);
    }

    /**
     * @param  array<string, string>  $filters
     */
    public function search(array $filters, ?Sort $sort, int $perPage): LengthAwarePaginator
    {
        $query = $this->filtered($filters)
            ->with(['vehicle.vehicleClass', 'party', 'site', 'operator', 'creator']);

        return $this->applySort($query, $sort)
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param  array<string, string>  $filters
     * @return array{hours: float, amount: float}
     */
    public function totals(array $filters): array
    {
        $row = $this->filtered($filters)
            ->toBase()
            ->selectRaw('COALESCE(SUM(hours), 0) AS hours, COALESCE(SUM(amount), 0) AS amount')
            ->first();

        return [
            'hours' => (float) $row->hours,
            'amount' => (float) $row->amount,
        ];
    }

    public function lastOperatorRate(string $operatorId): ?string
    {
        $rate = $this->query()
            ->where('operator_id', $operatorId)
            ->whereNotNull('operator_rate')
            ->orderByDesc('worked_on')
            ->orderByDesc('created_at')
            ->value('operator_rate');

        return $rate === null ? null : (string) $rate;
    }

    public function ticketTaken(string $ticketNo): bool
    {
        return $this->query()->where('ticket_no', $ticketNo)->exists();
    }

    /**
     * @return LazyCollection<int, MachineWork>
     */
    public function forVehicleSince(string $vehicleId, CarbonInterface $from): LazyCollection
    {
        return $this->query()
            ->where('vehicle_id', $vehicleId)
            ->whereDate('worked_on', '>=', $from->toDateString())
            ->orderBy('worked_on')
            ->lazyById();
    }

    /**
     * Active machines (vehicles of an hourly class) whose plate contains the term.
     *
     * @return Collection<int, Vehicle>
     */
    public function machines(string $term, int $limit = 10): Collection
    {
        return Vehicle::query()
            ->with(['currentOwnership.party', 'currentRate', 'vehicleClass'])
            ->where('active', true)
            ->whereHas('vehicleClass', fn (Builder $class) => $class->where('hourly', true))
            ->whereLike('plate_key', $this->likePattern(Vehicle::normalizePlate($term)))
            ->orderBy('plate')
            ->limit($limit)
            ->get();
    }

    protected function defaultSort(Builder $query): void
    {
        $query->orderByDesc('worked_on')->orderByDesc('created_at');
    }

    protected function sortColumns(): array
    {
        return [
            'ticket' => 'ticket_no',
            'plate' => fn (Builder $query, string $direction) => $query->orderBy(
                Vehicle::withTrashed()->select('plate_key')->whereColumn('vehicles.id', 'machine_works.vehicle_id'),
                $direction,
            ),
            'site' => fn (Builder $query, string $direction) => $query->orderBy(
                Site::withTrashed()->select('name')->whereColumn('sites.id', 'machine_works.site_id'),
                $direction,
            ),
            'hours' => 'hours',
            'amount' => 'amount',
            'worked_on' => 'worked_on',
            'created_at' => 'created_at',
        ];
    }

    /**
     * Without filters only the last few days are listed to keep data entry fast.
     *
     * @param  array<string, string>  $filters
     * @return Builder<MachineWork>
     */
    private function filtered(array $filters): Builder
    {
        $query = $this->query();

        if ($filters === []) {
            return $query->where('created_at', '>=', now()->subDays(self::RECENT_DAYS)->startOfDay());
        }

        return $query
            ->when($filters['site'] ?? null, fn (Builder $query, string $site) => $query->where('site_id', $site))
            ->when($filters['owner'] ?? null, fn (Builder $query, string $owner) => $query->where('party_id', $owner))
            ->when($filters['operator'] ?? null, fn (Builder $query, string $operator) => $query->where('operator_id', $operator))
            ->when($filters['shift'] ?? null, fn (Builder $query, string $shift) => $query->where('shift', $shift))
            ->when($filters['creator'] ?? null, fn (Builder $query, string $creator) => $query->where('created_by', $creator))
            ->when($filters['worked_from'] ?? null, fn (Builder $query, string $from) => $query->whereDate('worked_on', '>=', $from))
            ->when($filters['worked_to'] ?? null, fn (Builder $query, string $to) => $query->whereDate('worked_on', '<=', $to))
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
