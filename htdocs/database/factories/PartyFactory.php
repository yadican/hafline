<?php

namespace Database\Factories;

use App\Enums\PartyRole;
use App\Enums\PartyType;
use App\Models\Party;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Party>
 */
class PartyFactory extends Factory
{
    protected $model = Party::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => PartyType::Person,
            'name' => fake()->firstName().' '.fake()->lastName(),
            'phone' => fake()->numerify('05#########'),
            'mobile' => fake()->optional()->numerify('05#########'),
            'address' => fake()->optional()->address(),
            'active' => true,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Party $party): void {
            if ($party->type === PartyType::Person) {
                $party->person()->firstOrCreate([], [
                    'first_name' => Str::beforeLast($party->name, ' '),
                    'last_name' => Str::afterLast($party->name, ' '),
                ]);
            } else {
                $party->company()->firstOrCreate([], ['title' => $party->name]);
            }
        });
    }

    public function person(): static
    {
        return $this->state(['type' => PartyType::Person]);
    }

    public function company(): static
    {
        return $this->state(fn () => [
            'type' => PartyType::Company,
            'name' => mb_strtoupper(fake()->company(), 'UTF-8'),
        ]);
    }

    public function withRole(PartyRole ...$roles): static
    {
        return $this->afterCreating(function (Party $party) use ($roles): void {
            foreach ($roles as $role) {
                $party->assignRole($role);
            }
        });
    }
}
