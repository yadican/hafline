<?php

namespace App\Models;

use App\Enums\Shift;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FieldHaulSession extends Model
{
    use HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'site_id',
        'dump_site_id',
        'shift',
        'locked_at',
        'closed_at',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Site, $this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class)->withTrashed();
    }

    /**
     * @return BelongsTo<DumpSite, $this>
     */
    public function dumpSite(): BelongsTo
    {
        return $this->belongsTo(DumpSite::class)->withTrashed();
    }

    /**
     * @return HasMany<Haul, $this>
     */
    public function hauls(): HasMany
    {
        return $this->hasMany(Haul::class);
    }

    public function isOpen(): bool
    {
        return $this->closed_at === null;
    }

    /**
     * @param  Builder<FieldHaulSession>  $query
     * @return Builder<FieldHaulSession>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('closed_at');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'shift' => Shift::class,
            'locked_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }
}
