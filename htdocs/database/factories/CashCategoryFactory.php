<?php

namespace Database\Factories;

use App\Models\CashCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashCategory>
 */
class CashCategoryFactory extends Factory
{
    protected $model = CashCategory::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'active' => true,
        ];
    }
}
