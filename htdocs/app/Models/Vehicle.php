<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\VehicleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['plate', 'vehicle_class_id', 'driver_name', 'active'])]
class Vehicle extends Model
{
    /** @use HasFactory<VehicleFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    public static function normalizePlate(string $plate): string
    {
        return (string) preg_replace('/\s+/u', '', mb_strtoupper($plate, 'UTF-8'));
    }

    public static function findByPlate(string $plate): ?self
    {
        $key = self::normalizePlate($plate);

        if ($key === '') {
            return null;
        }

        return self::query()->where('plate_key', $key)->first();
    }

    /**
     * @return HasMany<VehicleOwnership, $this>
     */
    public function ownerships(): HasMany
    {
        return $this->hasMany(VehicleOwnership::class)->orderByDesc('started_on');
    }

    /**
     * @return HasOne<VehicleOwnership, $this>
     */
    public function currentOwnership(): HasOne
    {
        return $this->hasOne(VehicleOwnership::class)->whereNull('ended_on');
    }

    public function ownershipOn(CarbonInterface $date): ?VehicleOwnership
    {
        return $this->hasMany(VehicleOwnership::class)->covering($date)->first();
    }

    /**
     * The start of the latest ownership whose transfer closed the previous owner's account on this
     * plate; nothing before it may change. Transfers recorded without a closing do not lock.
     */
    public function closedBefore(): ?CarbonInterface
    {
        return $this->hasMany(VehicleOwnership::class)
            ->whereNotNull('carried_from_party_id')
            ->orderByDesc('started_on')
            ->first()
            ?->started_on;
    }

    /**
     * @return HasMany<VehicleRate, $this>
     */
    public function rates(): HasMany
    {
        return $this->hasMany(VehicleRate::class)->orderByDesc('started_on');
    }

    /**
     * @return HasOne<VehicleRate, $this>
     */
    public function currentRate(): HasOne
    {
        return $this->hasOne(VehicleRate::class)->whereNull('ended_on');
    }

    public function rateOn(CarbonInterface $date): ?VehicleRate
    {
        return $this->hasMany(VehicleRate::class)->covering($date)->first();
    }

    /**
     * Machines are billed by the hour through machine work tickets instead of by trip.
     */
    public function isHourly(): bool
    {
        return (bool) $this->vehicleClass?->hourly;
    }

    /**
     * @return BelongsTo<VehicleClass, $this>
     */
    public function vehicleClass(): BelongsTo
    {
        return $this->belongsTo(VehicleClass::class);
    }

    /**
     * @return Attribute<string, string>
     */
    protected function plate(): Attribute
    {
        return Attribute::set(fn (string $value) => [
            'plate' => mb_strtoupper(trim($value), 'UTF-8'),
            'plate_key' => self::normalizePlate($value),
        ]);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }
}
