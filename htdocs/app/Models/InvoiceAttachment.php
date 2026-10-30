<?php

namespace App\Models;

use Database\Factories\InvoiceAttachmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A picture or PDF of an invoice, stored on the data disk.
 */
#[Fillable([
    'invoice_id',
    'path',
    'created_by',
])]
class InvoiceAttachment extends Model
{
    /** @use HasFactory<InvoiceAttachmentFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class)->withTrashed();
    }

    public function isPdf(): bool
    {
        return strtolower(pathinfo($this->path, PATHINFO_EXTENSION)) === 'pdf';
    }
}
