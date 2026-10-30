<?php

namespace App\Models;

use App\Enums\InvoiceDirection;
use App\Enums\InvoicePaymentStatus;
use App\Enums\Module;
use App\Services\Ledger\LedgerSource;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A purchase, sale or return invoice. subtotal, vat_amount and total are the sums of the lines;
 * due_on only reminds and never affects a balance. A draft is not posted to cari until approved
 * and cannot be paid meanwhile. A live return may point at an open purchase or sale via
 * returns_invoice_id; that return's total counts toward the target's paid amount.
 */
#[Fillable([
    'direction',
    'returns_invoice_id',
    'party_id',
    'vehicle_id',
    'invoice_no',
    'issued_on',
    'due_on',
    'reminder_dismissed_at',
    'subtotal',
    'vat_amount',
    'total',
    'site_id',
    'description',
    'is_draft',
    'created_by',
])]
class Invoice extends Model implements LedgerSource
{
    /** @use HasFactory<InvoiceFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    public function ledgerModule(): Module
    {
        return Module::General;
    }

    public function ledgerSiteId(): ?string
    {
        return $this->site_id;
    }

    /**
     * @return BelongsTo<Party, $this>
     */
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class)->withTrashed();
    }

    /**
     * Plate a sale was billed to; null for cari sales and all non-sale invoices.
     *
     * @return BelongsTo<Vehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Site, $this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<InvoiceLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class)->orderBy('sort_order');
    }

    /**
     * @return HasMany<InvoiceAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(InvoiceAttachment::class)->orderBy('created_at')->orderBy('id');
    }

    /**
     * @return MorphMany<LedgerEntry, $this>
     */
    public function ledgerEntries(): MorphMany
    {
        return $this->morphMany(LedgerEntry::class, 'source');
    }

    /**
     * @return HasMany<InvoicePayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(InvoicePayment::class);
    }

    /**
     * Returns that reduce this invoice's remaining (purchase or sale returns linked here).
     *
     * @return HasMany<Invoice, $this>
     */
    public function returns(): HasMany
    {
        return $this->hasMany(self::class, 'returns_invoice_id');
    }

    /**
     * The open purchase or sale this return reduces, when linked.
     *
     * @return BelongsTo<Invoice, $this>
     */
    public function returnedInvoice(): BelongsTo
    {
        return $this->belongsTo(self::class, 'returns_invoice_id')->withTrashed();
    }

    /**
     * Payments matched plus live (non-draft) returns linked to this invoice. Lists load both sums
     * up front; otherwise they are loaded on first use.
     */
    public function paidAmount(): float
    {
        if (! array_key_exists('paid_amount', $this->attributes)) {
            $this->loadSum('payments as paid_amount', 'amount');
        }

        if (! array_key_exists('returned_amount', $this->attributes)) {
            $this->loadSum(['returns as returned_amount' => fn ($query) => $query->where('is_draft', false)], 'total');
        }

        return round((float) $this->getAttribute('paid_amount') + (float) $this->getAttribute('returned_amount'), 2);
    }

    public function remainingAmount(): float
    {
        return round((float) $this->total - $this->paidAmount(), 2);
    }

    /**
     * Matrah-side stock cost of a sale: sum of frozen unit_cost × quantity. Null for purchases,
     * drafts, and sales with no costed lines yet.
     */
    public function costAmount(): ?float
    {
        if ($this->direction !== InvoiceDirection::Sale) {
            return null;
        }

        $this->loadMissing('lines');

        $cost = 0.0;
        $hasCost = false;

        foreach ($this->lines as $line) {
            if ($line->unit_cost === null) {
                continue;
            }

            $hasCost = true;
            $cost += round((float) $line->quantity * (float) $line->unit_cost, 2);
        }

        return $hasCost ? round($cost, 2) : null;
    }

    /**
     * Sale matrah minus frozen stock cost. Null when cost is not known yet.
     */
    public function grossProfit(): ?float
    {
        $cost = $this->costAmount();

        if ($cost === null) {
            return null;
        }

        return round((float) $this->subtotal - $cost, 2);
    }

    public function paymentStatus(): InvoicePaymentStatus
    {
        return InvoicePaymentStatus::for((float) $this->total, $this->paidAmount());
    }

    public function isDraft(): bool
    {
        return (bool) $this->is_draft;
    }

    public function isOverdue(): bool
    {
        return ! $this->isDraft()
            && $this->due_on !== null
            && $this->due_on->isBefore(today())
            && $this->remainingAmount() > 0;
    }

    /**
     * Still listed on the dashboard Alacaklar / Borçlar panel: live invoice, due date set, remaining
     * open, not closed by hand.
     */
    public function remindsDue(): bool
    {
        return ! $this->isDraft()
            && $this->due_on !== null
            && $this->reminder_dismissed_at === null
            && $this->remainingAmount() > 0;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'direction' => InvoiceDirection::class,
            'issued_on' => 'date',
            'due_on' => 'date',
            'reminder_dismissed_at' => 'datetime',
            'subtotal' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'is_draft' => 'boolean',
        ];
    }
}
