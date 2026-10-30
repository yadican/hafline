<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\InvoiceLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoiceLine>
 */
class InvoiceLineFactory extends Factory
{
    protected $model = InvoiceLine::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'description' => fake()->words(3, true),
            'quantity' => fake()->numberBetween(1, 10),
            'unit_price' => fake()->numberBetween(50, 5000),
            'vat_rate' => 0,
            'subtotal' => fn (array $attributes) => round((float) $attributes['quantity'] * (float) $attributes['unit_price'], 2),
            'vat_amount' => fn (array $attributes) => round((float) $attributes['subtotal'] * (float) $attributes['vat_rate'] / 100, 2),
            'total' => fn (array $attributes) => round((float) $attributes['subtotal'] + (float) $attributes['vat_amount'], 2),
            'cash_category_id' => null,
            'sort_order' => 0,
        ];
    }

    public function vat(float $rate): static
    {
        return $this->state(['vat_rate' => $rate]);
    }
}
