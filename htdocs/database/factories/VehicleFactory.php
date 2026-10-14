<?php

namespace Database\Factories;

use App\Models\Vehicle;
use App\Models\VehicleClass;
use App\Models\VehicleOwner;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vehicle>
 */
class VehicleFactory extends Factory
{
    protected $model = Vehicle::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'plate' => fake()->unique()->bothify('34 ?? ####'),
            'owner_id' => VehicleOwner::factory(),
            'vehicle_class_id' => VehicleClass::factory(),
            'driver_name' => fake()->name(),
            'active' => true,
        ];
    }
}
