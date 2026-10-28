<?php

namespace Database\Factories;

use App\Enums\PartyRole;
use App\Models\Party;
use App\Models\Quarry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Quarry>
 */
class QuarryFactory extends Factory
{
    protected $model = Quarry::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => mb_strtoupper(fake()->unique()->city(), 'UTF-8').' OCAĞI',
            'party_id' => Party::factory()->company()->withRole(PartyRole::Quarry),
            'active' => true,
        ];
    }

    /**
     * A loading place with no quarry firm, usable only for transport.
     */
    public function loadingPlace(): static
    {
        return $this->state(['party_id' => null]);
    }
}
