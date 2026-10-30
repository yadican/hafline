<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceGrossProfitTest extends TestCase
{
    use RefreshDatabase;

    public function test_sale_gross_profit_is_matrah_minus_frozen_unit_cost_times_quantity(): void
    {
        $invoice = Invoice::factory()
            ->sale()
            ->has(InvoiceLine::factory()->state([
                'quantity' => 10,
                'unit_price' => 60,
                'unit_cost' => 43,
            ]), 'lines')
            ->has(InvoiceLine::factory()->state([
                'quantity' => 2,
                'unit_price' => 100,
                'unit_cost' => 80,
                'sort_order' => 1,
            ]), 'lines')
            ->create()
            ->fresh(['lines']);

        // Matrah 600 + 200; maliyet (10×43) + (2×80) = 430 + 160.
        $this->assertSame('800.00', $invoice->subtotal);
        $this->assertSame(590.0, $invoice->costAmount());
        $this->assertSame(210.0, $invoice->grossProfit());
    }

    public function test_purchase_draft_and_uncosted_sale_have_no_gross_profit(): void
    {
        $purchase = Invoice::factory()->create(['subtotal' => 500]);
        $draft = Invoice::factory()->sale()->draft()->create(['subtotal' => 500]);
        $uncosted = Invoice::factory()->sale()->create(['subtotal' => 500]);

        $this->assertNull($purchase->costAmount());
        $this->assertNull($purchase->grossProfit());
        $this->assertNull($draft->fresh(['lines'])->costAmount());
        $this->assertNull($draft->fresh(['lines'])->grossProfit());
        $this->assertNull($uncosted->fresh(['lines'])->costAmount());
        $this->assertNull($uncosted->fresh(['lines'])->grossProfit());
    }

    public function test_sale_detail_and_list_show_gross_profit(): void
    {
        $admin = User::factory()->yonetici()->create();
        $invoice = Invoice::factory()
            ->sale()
            ->has(InvoiceLine::factory()->state([
                'quantity' => 50,
                'unit_price' => 60,
                'unit_cost' => 43,
            ]), 'lines')
            ->create(['invoice_no' => 'S-PROFIT-1'])
            ->fresh(['lines']);

        $this->assertSame(2150.0, $invoice->costAmount());
        $this->assertSame(850.0, $invoice->grossProfit());

        $this->actingAs($admin)
            ->get(route('admin.invoices.show', $invoice))
            ->assertOk()
            ->assertSeeInOrder(['Maliyet', '2.150,00 TL', 'Brüt kâr', '850,00 TL']);

        $this->actingAs($admin)
            ->get(route('admin.invoices.index'))
            ->assertOk()
            ->assertSeeInOrder(['S-PROFIT-1', 'Satış', '3.000,00 TL', '850,00 TL']);
    }
}
