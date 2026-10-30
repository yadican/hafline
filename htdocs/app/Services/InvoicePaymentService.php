<?php

namespace App\Services;

use App\Cache\DashboardCache;
use App\Enums\InvoiceDirection;
use App\Enums\PaymentMethod;
use App\Exceptions\OperationRejectedException;
use App\Models\FinancialInstrumentMovement;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\LedgerEntry;
use App\Repositories\FinancialInstrumentRepository;
use App\Repositories\InvoicePaymentRepository;
use App\Services\Ledger\Ledger;
use App\Services\Ledger\LedgerLine;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Matches payments to invoices. A payment is any cari entry whose live posting on the invoice's
 * party moved money towards settling it: a debit with a payment method for a purchase (we paid the
 * supplier), a credit with a payment method for a sale (the customer paid us). A match never takes
 * more than the invoice's remaining amount or the payment's unmatched amount.
 */
class InvoicePaymentService extends Service
{
    public function __construct(
        private InvoicePaymentRepository $matches,
        private FinancialInstrumentRepository $instruments,
        private Ledger $ledger,
    ) {}

    /**
     * Settles the invoice with the payment. Without an amount the largest possible part is matched:
     * the invoice's remaining or the payment's unmatched amount, whichever is smaller. Matching the
     * same payment again adds to its match.
     */
    public function match(Invoice $invoice, Model $payment, string|float|null $amount = null, ?string $userId = null): InvoicePayment
    {
        return $this->transaction(function () use ($invoice, $payment, $amount, $userId): InvoicePayment {
            $locked = $this->lockInvoice($invoice);
            $payment = $this->lockPayment($payment);
            $label = mb_strtolower($locked->direction->paymentLabel(), 'UTF-8');

            if ($locked->isDraft()) {
                throw new OperationRejectedException('Taslak fatura ödenemez; önce onaylayın.');
            }

            if ($this->paymentAmount($payment, $locked) <= 0) {
                throw new OperationRejectedException(sprintf('Bu kayıt, faturanın carisine yapılmış bir %s değil.', $label));
            }

            $remaining = $locked->remainingAmount();
            $unmatched = $this->unmatchedAmount($payment, $locked);
            $amount = round($amount === null ? min($remaining, $unmatched) : (float) $amount, 2);

            if ($remaining <= 0) {
                throw new OperationRejectedException('Faturanın kalanı yok.');
            }

            if ($unmatched <= 0) {
                throw new OperationRejectedException(sprintf('Bu %s tamamen başka faturalara bağlanmış.', $label));
            }

            if ($amount <= 0) {
                throw new OperationRejectedException('Bağlanan tutar sıfırdan büyük olmalı.');
            }

            if ($amount > $remaining) {
                throw new OperationRejectedException(sprintf('Bağlanan tutar faturanın kalanını (%s) aşamaz.', $this->money($remaining)));
            }

            if ($amount > $unmatched) {
                throw new OperationRejectedException(sprintf('Bağlanan tutar, %s tutarının bağlanmamış kısmını (%s) aşamaz.', $label, $this->money($unmatched)));
            }

            $match = $this->matches->between($locked, $payment);

            if ($match) {
                $match->update(['amount' => LedgerLine::money((float) $match->amount + $amount)]);
            } else {
                $match = $locked->payments()->create([
                    'payment_type' => $payment->getMorphClass(),
                    'payment_id' => $payment->getKey(),
                    'amount' => LedgerLine::money($amount),
                    'created_by' => $userId,
                ]);
            }

            unset($invoice->paid_amount);
            DashboardCache::forgetInvoiceReminders();

            return $match;
        });
    }

    /**
     * Settles the invoice's remaining with the party's unmatched payments, oldest first, each taking
     * as much as it can. Returns the matches written.
     *
     * @return Collection<int, InvoicePayment>
     */
    public function distribute(Invoice $invoice, ?string $userId = null): Collection
    {
        return $this->transaction(function () use ($invoice, $userId): Collection {
            $locked = $this->lockInvoice($invoice);
            $matched = new Collection;

            if ($locked->remainingAmount() <= 0) {
                throw new OperationRejectedException('Faturanın kalanı yok.');
            }

            foreach ($this->matches->unmatchedPayments($locked) as $row) {
                if ($locked->remainingAmount() <= 0) {
                    break;
                }

                $matched->push($this->match($locked, $row['payment'], null, $userId));
            }

            if ($matched->isEmpty()) {
                throw new OperationRejectedException(sprintf('Faturaya bağlanacak boşta %s yok.', mb_strtolower($locked->direction->paymentLabel(), 'UTF-8')));
            }

            unset($invoice->paid_amount);
            DashboardCache::forgetInvoiceReminders();

            return $matched;
        });
    }

    /**
     * The party's payments with a part not yet matched to any invoice, oldest first.
     *
     * @return list<array{payment: Model, occurred_on: CarbonInterface, payment_method: ?PaymentMethod, description: ?string, amount: float, unmatched: float}>
     */
    public function unmatchedPayments(Invoice $invoice): array
    {
        return $this->matches->unmatchedPayments($invoice);
    }

    /**
     * The match no longer holds; the invoice's remaining grows back by its amount.
     */
    public function unmatch(InvoicePayment $match): void
    {
        $this->transaction(function () use ($match): void {
            $match->delete();
            DashboardCache::forgetInvoiceReminders();
        });
    }

    /**
     * The payment no longer pays anything (deleted, or a cheque returned unpaid): all its matches go
     * and the invoices it settled open again. A return unpaid marks the matches with its step, so
     * taking it back can bring them back. Returns the released matches, still loadable with trashed.
     *
     * @return Collection<int, InvoicePayment>
     */
    public function releasePayment(Model $payment, ?FinancialInstrumentMovement $bounce = null): Collection
    {
        return $this->transaction(function () use ($payment, $bounce): Collection {
            $this->lockPayment($payment);
            $matches = $this->matches->forPayment($payment, lock: true);

            foreach ($matches as $match) {
                if ($bounce) {
                    $match->update(['released_by_movement_id' => $bounce->getKey()]);
                }

                $match->delete();
            }

            if ($matches->isNotEmpty()) {
                DashboardCache::forgetInvoiceReminders();
            }

            return $matches;
        });
    }

    /**
     * The return unpaid was taken back: the matches it released come back, oldest first. Each is cut
     * to what its invoice still has open (another payment may have settled it meanwhile) and to the
     * payment's unmatched amount; one with nothing left to settle stays released. Returns the matches brought back.
     *
     * @return Collection<int, InvoicePayment>
     */
    public function restoreBounced(FinancialInstrumentMovement $bounce): Collection
    {
        return $this->transaction(function () use ($bounce): Collection {
            $restored = new Collection;

            foreach ($this->matches->releasedBy($bounce, lock: true) as $match) {
                if (! $match->payment) {
                    continue;
                }

                $invoice = $this->lockInvoice($match->invoice);
                $payment = $this->lockPayment($match->payment);
                $fits = round(min((float) $match->amount, $invoice->remainingAmount(), $this->unmatchedAmount($payment, $invoice)), 2);

                if ($fits <= 0) {
                    continue;
                }

                $match->fill(['amount' => LedgerLine::money($fits), 'released_by_movement_id' => null])->restore();
                $restored->push($match);
            }

            if ($restored->isNotEmpty()) {
                DashboardCache::forgetInvoiceReminders();
            }

            return $restored;
        });
    }

    /**
     * The payment changed (a smaller amount, another party, side or method): its matches are cut back
     * to what it pays now. The oldest match keeps its amount longest, so the newest is cut first; a
     * match cut to zero goes. Returns the matches cut or removed.
     *
     * @return Collection<int, InvoicePayment>
     */
    public function fitPayment(Model $payment): Collection
    {
        return $this->transaction(function () use ($payment): Collection {
            $this->lockPayment($payment);
            $matches = $this->matches->forPayment($payment, lock: true)->load('invoice');
            $paymentAmounts = [];
            $matched = 0.0;
            $changed = new Collection;

            foreach ($matches as $match) {
                $key = $match->invoice->party_id.'|'.$match->invoice->direction->value;
                $paymentAmounts[$key] ??= $this->paymentAmount($payment, $match->invoice);
                $fits = round(min((float) $match->amount, max(0, $paymentAmounts[$key] - $matched)), 2);
                $matched = round($matched + $fits, 2);

                if ($fits >= (float) $match->amount) {
                    continue;
                }

                if ($fits <= 0) {
                    $match->delete();
                } else {
                    $match->update(['amount' => LedgerLine::money($fits)]);
                }

                $changed->push($match);
            }

            if ($changed->isNotEmpty()) {
                DashboardCache::forgetInvoiceReminders();
            }

            return $changed;
        });
    }

    /**
     * What the payment paid towards invoices of this party and direction, from its live postings.
     * A payment whose cheque or note returned unpaid pays nothing: the return posts the reversal on
     * its own step, while the payment's postings stay as they were.
     */
    public function paymentAmount(Model $payment, Invoice $invoice): float
    {
        if ($this->instruments->bouncedFor($payment)) {
            return 0.0;
        }

        $postings = $this->ledger->live($payment)
            ->filter(fn (LedgerEntry $entry) => $entry->party_id === $invoice->party_id && $entry->payment_method !== null);
        $debit = (float) $postings->sum('debit');
        $credit = (float) $postings->sum('credit');

        return round(max(0, $invoice->direction === InvoiceDirection::Purchase ? $debit - $credit : $credit - $debit), 2);
    }

    /**
     * The part of the payment not yet matched to any invoice.
     */
    public function unmatchedAmount(Model $payment, Invoice $invoice): float
    {
        return round(max(0, $this->paymentAmount($payment, $invoice) - $this->matches->matchedToPayment($payment)), 2);
    }

    /**
     * An invoice with payments matched keeps its party and direction, and its total cannot drop
     * below what is matched. Callers must be inside a transaction.
     */
    public function assertInvoiceKeepsItsPayments(Invoice $invoice, string $partyId, InvoiceDirection $direction, string|float $total): void
    {
        $locked = $this->lockInvoice($invoice);
        $locked->loadSum('payments as paid_amount', 'amount');
        $locked->loadSum(['returns as returned_amount' => fn ($query) => $query->where('is_draft', false)], 'total');
        $matched = round((float) $locked->getAttribute('paid_amount'), 2);
        $returned = round((float) $locked->getAttribute('returned_amount'), 2);
        $settled = round($matched + $returned, 2);

        if ($settled <= 0) {
            return;
        }

        if ($locked->party_id !== $partyId || $locked->direction !== $direction) {
            throw new OperationRejectedException('Ödeme veya iade bağlanmış faturanın carisi veya yönü değiştirilemez.');
        }

        if ($matched > 0 && round((float) $total, 2) < $matched) {
            throw new OperationRejectedException(sprintf('Fatura toplamı, bağlanan ödemelerin toplamından (%s) az olamaz.', $this->money($matched)));
        }
    }

    /**
     * An invoice with payments matched cannot be deleted; its payments would be left matched to nothing.
     * Callers must be inside a transaction.
     */
    public function assertInvoiceHasNoPayments(Invoice $invoice): void
    {
        $locked = $this->lockInvoice($invoice);

        if (! array_key_exists('paid_amount', $locked->getAttributes())) {
            $locked->loadSum('payments as paid_amount', 'amount');
        }

        if ((float) $locked->getAttribute('paid_amount') > 0) {
            $label = $locked->direction->paymentLabel();

            throw new OperationRejectedException(sprintf(
                '%s bağlanmış fatura silinemez. Önce faturaya bağlı %s kayıtlarını Cari hareketleri ekranından silin.',
                $label,
                mb_strtolower($label, 'UTF-8'),
            ));
        }
    }

    /**
     * A purchase or sale with live returns cannot shrink below their total. Callers must be inside
     * a transaction.
     */
    public function assertInvoiceKeepsItsReturns(Invoice $invoice, string|float $total): void
    {
        $locked = $this->lockInvoice($invoice);
        $locked->loadSum(['returns as returned_amount' => fn ($query) => $query->where('is_draft', false)], 'total');
        $returned = round((float) $locked->getAttribute('returned_amount'), 2);

        if ($returned <= 0) {
            return;
        }

        if (round((float) $total, 2) < $returned) {
            throw new OperationRejectedException(sprintf(
                'Fatura toplamı, bağlı iade faturalarının toplamından (%s) az olamaz.',
                $this->money($returned),
            ));
        }
    }

    /**
     * An invoice with live returns cannot be deleted while those returns still point at it.
     * Callers must be inside a transaction.
     */
    public function assertInvoiceHasNoReturns(Invoice $invoice): void
    {
        $locked = $this->lockInvoice($invoice);
        $locked->loadSum(['returns as returned_amount' => fn ($query) => $query->where('is_draft', false)], 'total');

        if ((float) $locked->getAttribute('returned_amount') > 0) {
            throw new OperationRejectedException(
                'İade bağlanmış fatura silinemez. Önce bağlı iade faturalarını silin.',
            );
        }
    }

    private function lockInvoice(Invoice $invoice): Invoice
    {
        return Invoice::query()->lockForUpdate()->find($invoice->getKey())
            ?? throw new OperationRejectedException('Fatura bulunamadı.');
    }

    /**
     * Matches are written while the payment row is locked, so two matches of one payment cannot
     * both see the same unmatched amount.
     */
    private function lockPayment(Model $payment): Model
    {
        return $payment->newQuery()->lockForUpdate()->find($payment->getKey())
            ?? throw new OperationRejectedException('Ödeme kaydı bulunamadı.');
    }

    private function money(float $amount): string
    {
        return number_format($amount, 2, ',', '.').' TL';
    }
}
