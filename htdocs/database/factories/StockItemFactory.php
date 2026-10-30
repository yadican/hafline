<?php

namespace Database\Factories;

use App\Enums\StockItemUnit;
use App\Models\StockItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockItem>
 */
class StockItemFactory extends Factory
{
    protected $model = StockItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'unit' => StockItemUnit::Piece,
            'tracks_stock' => true,
            'sales_price' => fake()->randomFloat(2, 10, 500),
            'active' => true,
        ];
    }

    public function untracked(): static
    {
        return $this->state(['tracks_stock' => false]);
    }
}
