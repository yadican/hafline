<?php

namespace App\Repositories;

use App\Enums\InstrumentDirection;
use App\Enums\InstrumentStatus;
use App\Models\FinancialInstrument;
use App\Models\FinancialInstrumentMovement;
use App\Support\Sort;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class FinancialInstrumentRepository extends Repository
{
    public function __construct(FinancialInstrument $model)
    {
        parent::__construct($model);
    }

    /**
     * @param  array{q?: string, payment_method?: string, direction?: string, status?: string, party?: string, due_from?: string, due_to?: string, overdue?: string}  $filters
     */
    public function search(array $filters, ?Sort $sort = null, int $perPage = 20): LengthAwarePaginator
    {
        return $this->applySort($this->filtered($filters)->with('party'), $sort)
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Open (still waiting) totals of the filtered list, per direction, plus how many are past due.
     *
     * @param  array<string, string>  $filters
     * @return array{received: float, received_count: int, given: float, given_count: int, overdue_count: int}
     */
    public function openTotals(array $filters): array
    {
        $rows = $this->filtered($filters)
            ->whereIn('status', InstrumentStatus::open())
            ->toBase()
            ->groupBy('direction')
            ->selectRaw('direction, COUNT(*) as total_count, COALESCE(SUM(amount), 0) as total_amount')
            ->get()
            ->keyBy('direction');

        $received = $rows->get(InstrumentDirection::Received->value);
        $given = $rows->get(InstrumentDirection::Given->value);

        return [
            'received' => round((float) ($received->total_amount ?? 0), 2),
            'received_count' => (int) ($received->total_count ?? 0),
            'given' => round((float) ($given->total_amount ?? 0), 2),
            'given_count' => (int) ($given->total_count ?? 0),
            'overdue_count' => $this->filtered($filters)
                ->whereIn('status', InstrumentStatus::open())
                ->where('due_on', '<', Carbon::today()->toDateString())
                ->count(),
        ];
    }

    /**
     * Open cheques and notes falling due up to $to (from $from on, or everything already waiting
     * when $from is null), soonest first, with what is to be collected and paid in that window.
     *
     * @return array{items: Collection<int, FinancialInstrument>, count: int, received: float, given: float}
     */
    public function openDueBetween(?Carbon $from, Carbon $to, int $limit): array
    {
        $window = fn (): Builder => $this->query()
            ->whereIn('status', InstrumentStatus::open())
            ->when($from, fn (Builder $query, Carbon $from) => $query->where('due_on', '>=', $from->toDateString()))
            ->where('due_on', '<', $to->copy()->addDay()->toDateString());

        $rows = $window()
            ->toBase()
            ->groupBy('direction')
            ->selectRaw('direction, COUNT(*) as total_count, COALESCE(SUM(amount), 0) as total_amount')
            ->get()
            ->keyBy('direction');

        $received = $rows->get(InstrumentDirection::Received->value);
        $given = $rows->get(InstrumentDirection::Given->value);

        return [
            'items' => $window()->with('party')->orderBy('due_on')->orderByDesc('amount')->limit($limit)->get(),
            'count' => (int) ($received->total_count ?? 0) + (int) ($given->total_count ?? 0),
            'received' => round((float) ($received->total_amount ?? 0), 2),
            'given' => round((float) ($given->total_amount ?? 0), 2),
        ];
    }

    /**
     * Whether the payment's paper returned unpaid: the cheque or note taken or given on the entry,
     * or the one an endorsement passed on.
     */
    public function bouncedFor(Model $payment): bool
    {
        $query = $payment instanceof FinancialInstrumentMovement
            ? $this->query()->whereKey($payment->financial_instrument_id)
            : $this->query()->where('source_type', $payment->getMorphClass())->where('source_id', $payment->getKey());

        return $query->where('status', InstrumentStatus::Bounced)->exists();
    }

    /**
     * @param  array<string, string>  $filters
     * @return Builder<FinancialInstrument>
     */
    private function filtered(array $filters): Builder
    {
        return $this->whereTerm($this->query(), $filters['q'] ?? null, ['number', 'bank_name', 'drawer', 'description'])
            ->when($filters['payment_method'] ?? null, fn (Builder $query, string $method) => $query->where('payment_method', $method))
            ->when($filters['direction'] ?? null, fn (Builder $query, string $direction) => $query->where('direction', $direction))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['party'] ?? null, fn (Builder $query, string $party) => $query->where('party_id', $party))
            ->when($filters['due_from'] ?? null, fn (Builder $query, string $from) => $query->where('due_on', '>=', $from))
            ->when($filters['due_to'] ?? null, fn (Builder $query, string $to) => $query->where('due_on', '<=', $to))
            ->when(($filters['overdue'] ?? null) === '1', fn (Builder $query) => $query
                ->whereIn('status', InstrumentStatus::open())
                ->where('due_on', '<', Carbon::today()->toDateString()));
    }

    protected function defaultSort(Builder $query): void
    {
        $query->orderBy('due_on');
    }

    protected function sortColumns(): array
    {
        return [
            'due_on' => 'due_on',
            'issued_on' => 'issued_on',
            'amount' => 'amount',
            'number' => 'number',
        ];
    }
}
