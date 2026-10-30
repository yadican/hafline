<?php

namespace Database\Factories;

use App\Models\StockCount;
use App\Models\StockItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockCount>
 */
class StockCountFactory extends Factory
{
    protected $model = StockCount::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = fake()->randomFloat(3, 0, 100);
        $difference = fake()->randomFloat(3, -20, 20);

        return [
            'stock_item_id' => StockItem::factory(),
            'counted_on' => fake()->date(),
            'quantity' => $quantity,
            'difference' => $difference,
            'description' => null,
        ];
    }
}
