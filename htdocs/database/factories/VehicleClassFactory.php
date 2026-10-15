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
            'hourly' => false,
            'active' => true,
        ];
    }

    public function hourly(): static
    {
        return $this->state(['name' => 'MAKİNE '.fake()->unique()->numberBetween(1, 9999), 'hourly' => true]);
    }
}
