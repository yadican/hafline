<?php

namespace App\Models;

use Database\Factories\InvoicePaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * The part of a payment (a cari entry on the invoice's party) that settles an invoice. Written only
 * through InvoicePaymentService, which keeps it within the invoice's remaining and the payment's unmatched amount.
 * A match released by a cheque or note returning unpaid names that step until it is taken back.
 */
#[Fillable([
    'invoice_id',
    'payment_type',
    'payment_id',
    'amount',
    'created_by',
    'released_by_movement_id',
])]
class InvoicePayment extends Model
{
    /** @use HasFactory<InvoicePaymentFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class)->withTrashed();
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function payment(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }
}
