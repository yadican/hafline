<?php

namespace Database\Factories;

use App\Models\VehicleClass;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VehicleClass>
 */
class VehicleClassFactory extends Factory
{
    protected $model = VehicleClass::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['TIR', 'KAMYON', 'DAMPERLİ', 'KIRKAYAK']).' '.fake()->unique()->numberBetween(1, 9999),
            'active' => true,
        ];
    }
}
