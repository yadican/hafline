<?php

namespace Database\Factories;

use App\Enums\InvoiceDirection;
use App\Enums\PartyRole;
use App\Models\Invoice;
use App\Models\Party;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An invoice always has lines and its header equals their sums: without lines one matching line
 * is added; with lines (`has(InvoiceLine::factory(), 'lines')`) the header is recomputed from them.
 *
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'direction' => InvoiceDirection::Purchase,
            'party_id' => Party::factory()->company()->withRole(PartyRole::Supplier),
            'invoice_no' => fake()->unique()->numerify('A-#####'),
            'issued_on' => today()->toDateString(),
            'due_on' => null,
            'subtotal' => fake()->numberBetween(100, 50000),
            'vat_amount' => 0,
            'total' => fn (array $attributes) => round((float) $attributes['subtotal'] + (float) $attributes['vat_amount'], 2),
            'site_id' => null,
            'description' => null,
            'is_draft' => false,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => ['is_draft' => true]);
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Invoice $invoice): void {
            $lines = $invoice->lines()->get();

            if ($lines->isEmpty()) {
                $subtotal = (float) $invoice->subtotal;

                $invoice->lines()->create([
                    'description' => fake()->words(3, true),
                    'quantity' => 1,
                    'unit_price' => $subtotal,
                    'vat_rate' => $subtotal > 0 ? round((float) $invoice->vat_amount / $subtotal * 100, 2) : 0,
                    'subtotal' => $subtotal,
                    'vat_amount' => $invoice->vat_amount,
                    'total' => $invoice->total,
                ]);

                return;
            }

            $invoice->update([
                'subtotal' => round($lines->sum(fn ($line) => (float) $line->subtotal), 2),
                'vat_amount' => round($lines->sum(fn ($line) => (float) $line->vat_amount), 2),
                'total' => round($lines->sum(fn ($line) => (float) $line->total), 2),
            ]);
        });
    }

    public function sale(): static
    {
        return $this->state(fn () => [
            'direction' => InvoiceDirection::Sale,
            'party_id' => Party::factory()->company()->withRole(PartyRole::Customer),
        ]);
    }

    public function purchaseReturn(?Invoice $purchase = null): static
    {
        return $this->state(fn () => [
            'direction' => InvoiceDirection::PurchaseReturn,
            'party_id' => $purchase?->party_id ?? Party::factory()->company()->withRole(PartyRole::Supplier),
            'returns_invoice_id' => $purchase?->id,
            'invoice_no' => fake()->unique()->numerify('IR-#####'),
        ]);
    }

    public function saleReturn(?Invoice $sale = null): static
    {
        return $this->state(fn () => [
            'direction' => InvoiceDirection::SaleReturn,
            'party_id' => $sale?->party_id ?? Party::factory()->company()->withRole(PartyRole::Customer),
            'returns_invoice_id' => $sale?->id,
            'invoice_no' => fake()->unique()->numerify('SR-#####'),
        ]);
    }

    public function vat(float $rate): static
    {
        return $this->state([
            'vat_amount' => fn (array $attributes) => round((float) $attributes['subtotal'] * $rate / 100, 2),
        ]);
    }
}
