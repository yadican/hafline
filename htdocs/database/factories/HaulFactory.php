<?php

namespace Database\Factories;

use App\Enums\HaulShift;
use App\Models\Haul;
use App\Models\HaulRoute;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Haul>
 */
class HaulFactory extends Factory
{
    protected $model = Haul::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ticket_no' => fake()->unique()->numerify('T20260#######'),
            'vehicle_id' => Vehicle::factory(),
            'owner_id' => fn (array $attributes) => Vehicle::query()->find($attributes['vehicle_id'])?->owner_id,
            'driver_name' => fn (array $attributes) => Vehicle::query()->find($attributes['vehicle_id'])?->driver_name,
            'route_id' => HaulRoute::factory(),
            'site_id' => fn (array $attributes) => HaulRoute::query()->find($attributes['route_id'])?->site_id,
            'dump_site_id' => fn (array $attributes) => HaulRoute::query()->find($attributes['route_id'])?->dump_site_id,
            'tonnage' => fake()->numberBetween(8, 30),
            'price' => fake()->randomFloat(2, 1500, 5000),
            'dump_price' => fake()->randomFloat(2, 200, 900),
            'shift' => fake()->randomElement(HaulShift::cases()),
            'hauled_at' => fake()->dateTimeBetween('-3 days'),
        ];
    }
}
