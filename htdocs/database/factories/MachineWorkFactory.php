<?php

namespace Database\Factories;

use App\Enums\Shift;
use App\Models\MachineWork;
use App\Models\Site;
use App\Models\Vehicle;
use App\Models\VehicleClass;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MachineWork>
 */
class MachineWorkFactory extends Factory
{
    protected $model = MachineWork::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ticket_no' => fake()->unique()->numerify('M20260#######'),
            'vehicle_id' => Vehicle::factory()->state(['vehicle_class_id' => VehicleClass::factory()->hourly()]),
            'party_id' => fn (array $attributes) => Vehicle::query()->find($attributes['vehicle_id'])?->currentOwnership?->party_id,
            'site_id' => Site::factory(),
            'operator_id' => null,
            'shift' => fake()->randomElement(Shift::cases()),
            'hours' => fake()->randomElement([4, 6, 8, 9.5, 10]),
            'hourly_rate' => fake()->randomElement([1200, 1500, 1800]),
            'worked_on' => fake()->dateTimeBetween('-3 days')->format('Y-m-d'),
        ];
    }
}
