<?php

namespace Database\Factories;

use App\Enums\Shift;
use App\Models\HaulRoute;
use App\Models\TicketHandover;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketHandover>
 */
class TicketHandoverFactory extends Factory
{
    protected $model = TicketHandover::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vehicle_id' => Vehicle::factory(),
            'route_id' => HaulRoute::factory(),
            'haul_count' => fake()->numberBetween(1, 10),
            'fuel_count' => fake()->numberBetween(0, 5),
            'description' => null,
            'handed_on' => today()->toDateString(),
            'shift' => fake()->randomElement(Shift::cases()),
        ];
    }
}
