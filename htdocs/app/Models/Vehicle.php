<?php

namespace App\Models;

use Database\Factories\VehicleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['plate', 'owner_id', 'vehicle_class_id', 'driver_name', 'active'])]
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
     * @return BelongsTo<VehicleOwner, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(VehicleOwner::class, 'owner_id');
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
