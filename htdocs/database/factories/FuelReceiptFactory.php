<?php

namespace Database\Factories;

use App\Enums\Shift;
use App\Models\FuelReceipt;
use App\Models\FuelStation;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FuelReceipt>
 */
class FuelReceiptFactory extends Factory
{
    protected $model = FuelReceipt::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ticket_no' => fake()->unique()->numerify('Y20260#######'),
            'vehicle_id' => Vehicle::factory(),
            'party_id' => fn (array $attributes) => Vehicle::query()->find($attributes['vehicle_id'])?->currentOwnership?->party_id,
            'fuel_station_id' => FuelStation::factory(),
            'fuel_party_id' => fn (array $attributes) => FuelStation::query()->find($attributes['fuel_station_id'])?->party_id,
            'site_id' => null,
            'quantity' => fake()->randomElement([40, 60, 80, 120]),
            'unit_price' => fn (array $attributes) => FuelStation::query()->find($attributes['fuel_station_id'])?->unit_price,
            'issued_on' => today()->toDateString(),
            'km' => fake()->numberBetween(10000, 400000),
            'received_by' => fake()->name(),
            'shift' => fake()->randomElement(Shift::cases()),
            'haul_ticket_count' => fake()->numberBetween(1, 6),
        ];
    }
}
