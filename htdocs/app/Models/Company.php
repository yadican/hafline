<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_group_id', 'title', 'tax_office', 'tax_number', 'fax'])]
class Company extends Model
{
    protected $primaryKey = 'party_id';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @return BelongsTo<Party, $this>
     */
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class)->withTrashed();
    }

    /**
     * @return BelongsTo<CompanyGroup, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(CompanyGroup::class, 'company_group_id')->withTrashed();
    }
}
