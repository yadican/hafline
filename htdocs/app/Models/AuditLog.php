<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    public const UPDATED_AT = null;

    public const CHANNEL_WEB = 'web';

    public const CHANNEL_API = 'api';

    /**
     * @var array<string, string>
     */
    public const ACTION_LABELS = [
        'created' => 'Oluşturma',
        'updated' => 'Güncelleme',
        'deleted' => 'Silme',
        'restored' => 'Geri alma',
        'login' => 'Giriş',
        'logout' => 'Çıkış',
        'submitted' => 'Gönderim',
        'viewed' => 'Görüntüleme',
    ];

    use HasUuids;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'payload' => 'array',
            'response' => 'array',
            'context' => 'array',
        ];
    }

    public function actionLabel(): string
    {
        return self::ACTION_LABELS[$this->action] ?? $this->action;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }
}
