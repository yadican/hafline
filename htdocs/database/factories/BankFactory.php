<?php

namespace Database\Factories;

use App\Enums\AccountType;
use App\Models\Bank;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Bank>
 */
class BankFactory extends Factory
{
    protected $model = Bank::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => AccountType::Bank,
            'name' => fake()->unique()->company().' Bankası',
            'representative' => fake()->optional()->name(),
            'phone' => fake()->optional()->numerify('0212#######'),
            'account_no' => fake()->optional()->numerify('TR## #### #### #### #### #### ##'),
            'active' => true,
        ];
    }
}
