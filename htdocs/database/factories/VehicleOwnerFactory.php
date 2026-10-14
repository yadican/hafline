<?php

namespace Database\Factories;

use App\Models\VehicleOwner;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VehicleOwner>
 */
class VehicleOwnerFactory extends Factory
{
    protected $model = VehicleOwner::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'phone' => fake()->numerify('05#########'),
            'mobile' => fake()->optional()->numerify('05#########'),
            'address' => fake()->optional()->address(),
        ];
    }
}
