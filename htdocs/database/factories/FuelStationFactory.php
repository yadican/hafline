<?php

namespace Database\Factories;

use App\Enums\FuelProduct;
use App\Enums\FuelSourceType;
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
            'type' => FuelSourceType::Station,
            'product' => FuelProduct::Diesel,
            'party_id' => Party::factory()->company()->withRole(PartyRole::Fuel),
            'purchase_price' => fake()->randomFloat(4, 35, 40),
            'unit_price' => fake()->randomFloat(4, 40, 55),
            'active' => true,
        ];
    }

    /**
     * Our own tank: no firm and no purchase price; cost comes from its fills.
     */
    public function tank(): static
    {
        return $this->state([
            'name' => mb_strtoupper(fake()->unique()->city(), 'UTF-8').' DEPO',
            'type' => FuelSourceType::Tank,
            'party_id' => null,
            'purchase_price' => 0,
        ]);
    }
}
