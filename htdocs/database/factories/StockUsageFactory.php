<?php

namespace Database\Factories;

use App\Models\CashCategory;
use App\Models\Site;
use App\Models\StockItem;
use App\Models\StockUsage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockUsage>
 */
class StockUsageFactory extends Factory
{
    protected $model = StockUsage::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = fake()->randomFloat(3, 1, 20);
        $unitCost = fake()->randomFloat(4, 10, 100);

        return [
            'stock_item_id' => StockItem::factory(),
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'amount' => round($quantity * $unitCost, 2),
            'used_on' => fake()->date(),
            'site_id' => Site::factory(),
            'vehicle_id' => null,
            'cash_category_id' => CashCategory::factory(),
            'description' => null,
        ];
    }
}
