<?php

namespace App\Models;

use App\Enums\Module;
use App\Services\Ledger\LedgerSource;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A progress bill (hakediş): what the site owes for a stretch of work, debited to the site's cari.
 */
#[Fillable(['site_id', 'party_id', 'occurred_on', 'amount', 'description', 'created_by'])]
class SiteBill extends Model implements LedgerSource
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
            'amount' => 'decimal:2',
            'occurred_on' => 'date',
        ];
    }
}
