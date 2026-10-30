<?php

namespace App\Repositories;

use App\Enums\InstrumentStatus;
use App\Enums\InvoiceDirection;
use App\Enums\PaymentMethod;
use App\Models\FinancialInstrument;
use App\Models\FinancialInstrumentMovement;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\LedgerEntry;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\JoinClause;

class InvoicePaymentRepository extends Repository
{
    public function __construct(InvoicePayment $model)
    {
        parent::__construct($model);
    }

    /**
     * The payment's live matches, oldest first; a match on a deleted invoice no longer holds the payment.
     *
     * @return Collection<int, InvoicePayment>
     */
    public function forPayment(Model $payment, bool $lock = false): Collection
    {
        return $this->query()
            ->where('payment_type', $payment->getMorphClass())
            ->where('payment_id', $payment->getKey())
            ->whereHas('invoice', fn (Builder $query) => $query->withoutTrashed())
            ->orderBy('created_at')
            ->orderBy('id')
            ->when($lock, fn (Builder $query) => $query->lockForUpdate())
            ->get();
    }

    /**
     * The matches the return unpaid released, oldest first; one on a deleted invoice stays released.
     *
     * @return Collection<int, InvoicePayment>
     */
    public function releasedBy(FinancialInstrumentMovement $bounce, bool $lock = false): Collection
    {
        return $this->query()
            ->onlyTrashed()
            ->where('released_by_movement_id', $bounce->getKey())
            ->whereHas('invoice', fn (Builder $query) => $query->withoutTrashed())
            ->orderBy('created_at')
            ->orderBy('id')
            ->when($lock, fn (Builder $query) => $query->lockForUpdate())
            ->get();
    }

    public function matchedToPayment(Model $payment): float
    {
        return round((float) $this->forPayment($payment)->sum('amount'), 2);
    }

    public function between(Invoice $invoice, Model $payment): ?InvoicePayment
    {
        return $invoice->payments()
            ->where('payment_type', $payment->getMorphClass())
            ->where('payment_id', $payment->getKey())
            ->first();
    }

    /**
     * The payments of the invoice's party and direction with a part not yet matched to any invoice,
     * oldest first. Amounts follow `InvoicePaymentService::paymentAmount`: live postings with a
     * payment method on the party, and nothing for a payment whose cheque or note returned unpaid.
     *
     * @return list<array{payment: Model, occurred_on: CarbonInterface, payment_method: ?PaymentMethod, description: ?string, amount: float, unmatched: float}>
     */
    public function unmatchedPayments(Invoice $invoice): array
    {
        $side = $invoice->direction === InvoiceDirection::Purchase ? 'debit - credit' : 'credit - debit';
        $movementType = (new FinancialInstrumentMovement)->getMorphClass();

        $paid = LedgerEntry::query()
            ->select(['source_type', 'source_id'])
            ->selectRaw('MIN(occurred_on) as occurred_on, MIN(payment_method) as payment_method, MIN(description) as description, MIN(created_at) as posted_at')
            ->selectRaw("SUM({$side}) as paid_amount")
            ->where('party_id', $invoice->party_id)
            ->whereNotNull('payment_method')
            ->whereNull('reversal_of')
            ->whereDoesntHave('reversal')
            ->whereNotExists(FinancialInstrument::query()
                ->whereColumn('financial_instruments.source_type', 'ledger_entries.source_type')
                ->whereColumn('financial_instruments.source_id', 'ledger_entries.source_id')
                ->where('status', InstrumentStatus::Bounced)
                ->toBase())
            ->whereNotExists(FinancialInstrumentMovement::query()
                ->whereColumn('financial_instrument_movements.id', 'ledger_entries.source_id')
                ->where('ledger_entries.source_type', $movementType)
                ->whereHas('instrument', fn (Builder $query) => $query->where('status', InstrumentStatus::Bounced))
                ->toBase())
            ->groupBy('source_type', 'source_id');

        $matched = $this->query()
            ->select(['payment_type', 'payment_id'])
            ->selectRaw('SUM(amount) as matched_amount')
            ->whereHas('invoice', fn (Builder $query) => $query->withoutTrashed())
            ->groupBy('payment_type', 'payment_id');

        $unmatched = 'paid.paid_amount - COALESCE(matched.matched_amount, 0)';

        return LedgerEntry::query()
            ->fromSub($paid, 'paid')
            ->leftJoinSub($matched, 'matched', fn (JoinClause $join) => $join
                ->on('matched.payment_type', '=', 'paid.source_type')
                ->on('matched.payment_id', '=', 'paid.source_id'))
            ->select('paid.*')
            ->selectRaw("{$unmatched} as unmatched_amount")
            ->whereRaw("{$unmatched} >= 0.01")
            ->orderBy('paid.occurred_on')
            ->orderBy('paid.posted_at')
            ->orderBy('paid.source_id')
            ->get()
            ->load('source')
            ->filter(fn (LedgerEntry $row) => $row->source !== null)
            ->map(fn (LedgerEntry $row) => [
                'payment' => $row->source,
                'occurred_on' => $row->occurred_on,
                'payment_method' => $row->payment_method,
                'description' => $row->description,
                'amount' => round((float) $row->getAttribute('paid_amount'), 2),
                'unmatched' => round((float) $row->getAttribute('unmatched_amount'), 2),
            ])
            ->values()
            ->all();
    }
}
