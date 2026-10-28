<?php

namespace App\Models;

use App\Enums\Module;
use App\Enums\PaymentMethod;
use App\Services\Ledger\LedgerSource;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Money in or out at a site. A collection (credit) from a site that has its own cari is posted to
 * that cari (party_id); an expense (debit) is a site cost and stays off any cari.
 */
#[Fillable([
    'site_id',
    'party_id',
    'payment_method',
    'bank_id',
    'debit',
    'credit',
    'occurred_on',
    'description',
    'created_by',
])]
class SiteTransaction extends Model implements LedgerSource
{
    use HasUuids, SoftDeletes;

    public function ledgerModule(): Module
    {
        return Module::Excavation;
    }

    public function ledgerSiteId(): ?string
    {
        return $this->site_id;
    }

    /**
     * @return BelongsTo<Site, $this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class)->withTrashed();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'occurred_on' => 'date',
            'debit' => 'decimal:2',
            'credit' => 'decimal:2',
            'payment_method' => PaymentMethod::class,
        ];
    }
}
