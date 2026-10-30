<?php

namespace App\Repositories;

use App\Enums\InvoiceDirection;
use App\Models\Invoice;
use App\Models\Party;
use App\Support\Sort;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\LazyCollection;

class InvoiceRepository extends Repository
{
    public function __construct(Invoice $model)
    {
        parent::__construct($model);
    }

    /**
     * @param  array<string, string>  $filters
     */
    public function search(array $filters, ?Sort $sort, int $perPage): LengthAwarePaginator
    {
        $query = $this->query()
            ->with(['party', 'vehicle', 'lines:id,invoice_id,quantity,unit_cost'])
            ->withCount('attachments')
            ->withSum('payments as paid_amount', 'amount')
            ->withSum(['returns as returned_amount' => fn (Builder $query) => $query->where('is_draft', false)], 'total')
            ->when($filters['direction'] ?? null, fn (Builder $query, string $direction) => $query->where('direction', $direction))
            ->when($filters['party'] ?? null, fn (Builder $query, string $party) => $query->where('party_id', $party))
            ->when($filters['from'] ?? null, fn (Builder $query, string $from) => $query->whereDate('issued_on', '>=', $from))
            ->when($filters['to'] ?? null, fn (Builder $query, string $to) => $query->whereDate('issued_on', '<=', $to))
            ->when($filters['due_from'] ?? null, fn (Builder $query, string $from) => $query->whereDate('due_on', '>=', $from))
            ->when($filters['due_to'] ?? null, fn (Builder $query, string $to) => $query->whereDate('due_on', '<=', $to))
            ->when(array_key_exists('draft', $filters), fn (Builder $query) => $query->where('is_draft', $filters['draft'] === '1'))
            ->when(($filters['overdue'] ?? null) === '1', fn (Builder $query) => $query
                ->where('is_draft', false)
                ->whereNotNull('due_on')
                ->whereDate('due_on', '<', Carbon::today()->toDateString())
                ->whereRaw('invoices.total > '.$this->paidSubquery()))
            ->when(
                ($filters['due_from'] ?? null) !== null || ($filters['due_to'] ?? null) !== null,
                fn (Builder $query) => $query->where('is_draft', false),
            );

        return $this->applySort($query, $sort)
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Unpaid invoices with a due date in the window (from $from on, or everything already overdue
     * when $from is null), soonest first. Excludes reminders closed by hand. Amount is remaining.
     *
     * @return array{items: Collection<int, Invoice>, count: int, amount: float}
     */
    public function openDueBetween(InvoiceDirection $direction, ?Carbon $from, Carbon $to, int $limit): array
    {
        $window = fn (): Builder => $this->reminding()
            ->where('direction', $direction)
            ->when($from, fn (Builder $query, Carbon $from) => $query->where('due_on', '>=', $from->toDateString()))
            ->where('due_on', '<', $to->copy()->addDay()->toDateString());

        $stats = $window()
            ->toBase()
            ->selectRaw('COUNT(*) as total_count, COALESCE(SUM(invoices.total - '.$this->paidSubquery().'), 0) as total_remaining')
            ->first();

        return [
            'items' => $window()
                ->with('party')
                ->withSum('payments as paid_amount', 'amount')
                ->withSum(['returns as returned_amount' => fn (Builder $query) => $query->where('is_draft', false)], 'total')
                ->orderBy('due_on')
                ->orderByDesc('total')
                ->limit($limit)
                ->get(),
            'count' => (int) ($stats->total_count ?? 0),
            'amount' => round((float) ($stats->total_remaining ?? 0), 2),
        ];
    }

    /**
     * Live invoice, due date set, remaining open, reminder not closed by hand.
     *
     * @return Builder<Invoice>
     */
    private function reminding(): Builder
    {
        return $this->query()
            ->where('is_draft', false)
            ->whereNotNull('due_on')
            ->whereNull('reminder_dismissed_at')
            ->whereRaw('invoices.total > '.$this->paidSubquery());
    }

    /**
     * Matched payments plus live purchase returns linked to this invoice.
     */
    private function paidSubquery(): string
    {
        return '(COALESCE((
            SELECT SUM(invoice_payments.amount)
            FROM invoice_payments
            WHERE invoice_payments.invoice_id = invoices.id
              AND invoice_payments.deleted_at IS NULL
        ), 0) + COALESCE((
            SELECT SUM(returns.total)
            FROM invoices returns
            WHERE returns.returns_invoice_id = invoices.id
              AND returns.deleted_at IS NULL
              AND returns.is_draft = false
        ), 0))';
    }

    /**
     * Open (remaining > 0) live purchases, grouped by party, for linking a purchase return.
     * Include keeps a currently linked purchase visible even if it is already fully covered.
     *
     * @return array<string, array<string, string>> party_id => [invoice_id => label]
     */
    public function returnablePurchases(?string $includeId = null): array
    {
        return $this->returnableInvoices(InvoiceDirection::Purchase, $includeId);
    }

    /**
     * Open live sales for linking a sale return (same shape as returnablePurchases).
     *
     * @return array<string, array<string, string>> party_id => [invoice_id => label]
     */
    public function returnableSales(?string $includeId = null): array
    {
        return $this->returnableInvoices(InvoiceDirection::Sale, $includeId);
    }

    /**
     * @return array<string, array<string, string>> party_id => [invoice_id => label]
     */
    private function returnableInvoices(InvoiceDirection $direction, ?string $includeId = null): array
    {
        $paid = $this->paidSubquery();

        return $this->query()
            ->with('party')
            ->where('direction', $direction)
            ->where('is_draft', false)
            ->where(fn (Builder $query) => $query
                ->whereRaw("invoices.total > {$paid}")
                ->when($includeId, fn (Builder $query) => $query->orWhere('invoices.id', $includeId)))
            ->orderByDesc('issued_on')
            ->orderBy('invoice_no')
            ->get(['id', 'party_id', 'invoice_no', 'issued_on', 'total'])
            ->groupBy('party_id')
            ->map(fn ($rows): array => $rows
                ->mapWithKeys(fn (Invoice $invoice): array => [
                    $invoice->id => sprintf(
                        '%s · %s · %s TL',
                        $invoice->invoice_no,
                        $invoice->issued_on->format('d.m.Y'),
                        number_format((float) $invoice->total, 2, ',', '.'),
                    ),
                ])
                ->all())
            ->all();
    }

    /**
     * Plate-sale invoices on this vehicle from $from on (inclusive), for ownership reassignment.
     *
     * @return LazyCollection<int, Invoice>
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
     * Replaces the invoice's lines with the given ones, in the given order.
     *
     * @param  list<array<string, mixed>>  $lines
     */
    public function replaceLines(Invoice $invoice, array $lines): void
    {
        $invoice->lines()->delete();

        foreach (array_values($lines) as $order => $line) {
            $invoice->lines()->create([...$line, 'sort_order' => $order]);
        }

        $invoice->unsetRelation('lines');
    }

    protected function defaultSort(Builder $query): void
    {
        $query->orderByDesc('issued_on')->latest();
    }

    protected function sortColumns(): array
    {
        return [
            'issued_on' => 'issued_on',
            'invoice_no' => 'invoice_no',
            'total' => 'total',
            'due_on' => 'due_on',
            'party' => fn (Builder $query, string $direction) => $query->orderBy(
                Party::withTrashed()->select('name')->whereColumn('parties.id', 'invoices.party_id'),
                $direction,
            ),
        ];
    }
}
