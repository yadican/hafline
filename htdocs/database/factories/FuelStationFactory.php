<?php

namespace Database\Factories;

use App\Models\FuelStation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FuelStation>
 */
class FuelStationFactory extends Factory
{
    protected $model = FuelStation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => mb_strtoupper(fake()->unique()->company(), 'UTF-8').' PETROL',
            'unit_price' => fake()->randomFloat(4, 40, 55),
            'site_id' => null,
            'active' => true,
        ];
    }
}
