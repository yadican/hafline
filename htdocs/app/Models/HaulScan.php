<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HaulScan extends Model
{
    use HasUuids;

    public const STATUS_PENDING = 'pending';

    public const STATUS_ATTACHED = 'attached';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'field_haul_session_id',
        'haul_id',
        'machine_work_id',
        'fuel_receipt_id',
        'path',
        'checksum',
        'status',
        'plate',
        'confidence',
        'expires_at',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<FieldHaulSession, $this>
     */
    public function fieldSession(): BelongsTo
    {
        return $this->belongsTo(FieldHaulSession::class, 'field_haul_session_id');
    }

    /**
     * @return BelongsTo<Haul, $this>
     */
    public function haul(): BelongsTo
    {
        return $this->belongsTo(Haul::class);
    }

    /**
     * @return BelongsTo<MachineWork, $this>
     */
    public function machineWork(): BelongsTo
    {
        return $this->belongsTo(MachineWork::class);
    }

    /**
     * @return BelongsTo<FuelReceipt, $this>
     */
    public function fuelReceipt(): BelongsTo
    {
        return $this->belongsTo(FuelReceipt::class);
    }

    /**
     * Not yet attached to a haul, machine work or fuel ticket.
     */
    public function isUnattached(): bool
    {
        return $this->haul_id === null && $this->machine_work_id === null && $this->fuel_receipt_id === null;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING && $this->expires_at->isFuture();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'confidence' => 'float',
            'expires_at' => 'datetime',
        ];
    }
}
