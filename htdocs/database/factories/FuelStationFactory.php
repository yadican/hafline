<?php

namespace Database\Factories;

use App\Enums\PartyRole;
use App\Models\FuelStation;
use App\Models\Party;
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
            'party_id' => Party::factory()->company()->withRole(PartyRole::Fuel),
            'unit_price' => fake()->randomFloat(4, 40, 55),
            'active' => true,
        ];
    }
}
