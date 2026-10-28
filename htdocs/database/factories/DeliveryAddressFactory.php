<?php

namespace Database\Factories;

use App\Enums\PartyRole;
use App\Models\DeliveryAddress;
use App\Models\Party;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeliveryAddress>
 */
class DeliveryAddressFactory extends Factory
{
    protected $model = DeliveryAddress::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'party_id' => Party::factory()->withRole(PartyRole::Customer),
            'name' => mb_strtoupper(fake()->unique()->streetName(), 'UTF-8'),
            'active' => true,
        ];
    }
}
