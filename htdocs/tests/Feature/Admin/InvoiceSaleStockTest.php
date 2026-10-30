<?php

namespace Tests\Feature\Admin;

use App\Models\Invoice;
use App\Models\Party;
use App\Models\StockItem;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceSaleStockTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->yonetici()->create();
    }

    public function test_sale_of_tracked_item_reduces_stock_at_average_cost(): void
    {
        $item = StockItem::factory()->create();
        $supplier = Party::factory()->company()->create();
        $customer = Party::factory()->company()->create();

        $this->actingAs($this->admin)
            ->post(route('admin.invoices.store'), [
                'direction' => 'purchase',
                'party_id' => $supplier->id,
                'invoice_no' => 'A-IN-1',
                'issued_on' => '2026-03-01',
                'is_draft' => false,
                'payment_status' => 'unpaid',
                'lines' => [[
                    'description' => $item->name,
                    'quantity' => '100',
                    'unit_price' => '40',
                    'vat_rate' => '0',
                    'stock_item_id' => $item->id,
                ]],
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->admin)
            ->post(route('admin.invoices.store'), [
                'direction' => 'purchase',
                'party_id' => $supplier->id,
                'invoice_no' => 'A-IN-2',
                'issued_on' => '2026-03-05',
                'is_draft' => false,
                'payment_status' => 'unpaid',
                'lines' => [[
                    'description' => $item->name,
                    'quantity' => '100',
                    'unit_price' => '46',
                    'vat_rate' => '0',
                    'stock_item_id' => $item->id,
                ]],
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->admin)
            ->post(route('admin.invoices.store'), [
                'direction' => 'sale',
                'party_id' => $customer->id,
                'invoice_no' => 'S-OUT-1',
                'issued_on' => '2026-03-10',
                'is_draft' => false,
                'payment_status' => 'unpaid',
                'lines' => [[
                    'description' => $item->name,
                    'quantity' => '50',
                    'unit_price' => '60',
                    'vat_rate' => '0',
                    'stock_item_id' => $item->id,
                ]],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.invoices.index'));

        $sale = Invoice::query()->where('invoice_no', 'S-OUT-1')->sole();
        $this->assertSame('43.0000', $sale->lines()->sole()->unit_cost);
        $this->assertSame(
            ['quantity' => 150.0, 'average_cost' => 43.0, 'value' => 6450.0],
            app(StockService::class)->stock($item),
        );
    }

    public function test_sale_unit_cost_stays_frozen_after_later_purchase(): void
    {
        $item = StockItem::factory()->create();
        $supplier = Party::factory()->company()->create();
        $customer = Party::factory()->company()->create();

        $this->actingAs($this->admin)
            ->post(route('admin.invoices.store'), [
                'direction' => 'purchase',
                'party_id' => $supplier->id,
                'invoice_no' => 'A-FREEZE-1',
                'issued_on' => '2026-03-01',
                'is_draft' => false,
                'payment_status' => 'unpaid',
                'lines' => [[
                    'description' => $item->name,
                    'quantity' => '10',
                    'unit_price' => '100',
                    'vat_rate' => '0',
                    'stock_item_id' => $item->id,
                ]],
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->admin)
            ->post(route('admin.invoices.store'), [
                'direction' => 'sale',
                'party_id' => $customer->id,
                'invoice_no' => 'S-FREEZE-1',
                'issued_on' => '2026-03-02',
                'is_draft' => false,
                'payment_status' => 'unpaid',
                'lines' => [[
                    'description' => $item->name,
                    'quantity' => '4',
                    'unit_price' => '150',
                    'vat_rate' => '0',
                    'stock_item_id' => $item->id,
                ]],
            ])
            ->assertSessionHasNoErrors();

        $saleLine = Invoice::query()->where('invoice_no', 'S-FREEZE-1')->sole()->lines()->sole();
        $this->assertSame('100.0000', $saleLine->unit_cost);

        $this->actingAs($this->admin)
            ->post(route('admin.invoices.store'), [
                'direction' => 'purchase',
                'party_id' => $supplier->id,
                'invoice_no' => 'A-FREEZE-2',
                'issued_on' => '2026-03-03',
                'is_draft' => false,
                'payment_status' => 'unpaid',
                'lines' => [[
                    'description' => $item->name,
                    'quantity' => '10',
                    'unit_price' => '200',
                    'vat_rate' => '0',
                    'stock_item_id' => $item->id,
                ]],
            ])
            ->assertSessionHasNoErrors();

        $saleLine->refresh();
        $this->assertSame('100.0000', $saleLine->unit_cost);
        $this->assertSame(
            ['quantity' => 16.0, 'average_cost' => 162.5, 'value' => 2600.0],
            app(StockService::class)->stock($item),
        );
    }

    public function test_draft_sale_does_not_reduce_stock_until_approved(): void
    {
        $item = StockItem::factory()->create();
        $supplier = Party::factory()->company()->create();
        $customer = Party::factory()->company()->create();

        $this->actingAs($this->admin)
            ->post(route('admin.invoices.store'), [
                'direction' => 'purchase',
                'party_id' => $supplier->id,
                'invoice_no' => 'A-DRAFT-SALE-IN',
                'issued_on' => today()->toDateString(),
                'is_draft' => false,
                'payment_status' => 'unpaid',
                'lines' => [[
                    'description' => $item->name,
                    'quantity' => '20',
                    'unit_price' => '50',
                    'vat_rate' => '0',
                    'stock_item_id' => $item->id,
                ]],
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->admin)
            ->post(route('admin.invoices.store'), [
                'direction' => 'sale',
                'party_id' => $customer->id,
                'invoice_no' => 'S-DRAFT-1',
                'issued_on' => today()->toDateString(),
                'is_draft' => true,
                'lines' => [[
                    'description' => $item->name,
                    'quantity' => '5',
                    'unit_price' => '80',
                    'vat_rate' => '0',
                    'stock_item_id' => $item->id,
                ]],
            ])
            ->assertSessionHasNoErrors();

        $invoice = Invoice::query()->where('invoice_no', 'S-DRAFT-1')->sole();
        $this->assertNull($invoice->lines()->sole()->unit_cost);
        $this->assertSame(
            ['quantity' => 20.0, 'average_cost' => 50.0, 'value' => 1000.0],
            app(StockService::class)->stock($item),
        );

        $this->actingAs($this->admin)
            ->post(route('admin.invoices.approve', $invoice))
            ->assertSessionHas('success');

        $invoice->refresh();
        $this->assertSame('50.0000', $invoice->lines()->sole()->unit_cost);
        $this->assertSame(
            ['quantity' => 15.0, 'average_cost' => 50.0, 'value' => 750.0],
            app(StockService::class)->stock($item),
        );
    }

    public function test_same_day_purchase_is_available_for_same_day_sale(): void
    {
        $item = StockItem::factory()->create();
        $supplier = Party::factory()->company()->create();
        $customer = Party::factory()->company()->create();
        $day = '2026-03-15';

        $this->actingAs($this->admin)
            ->post(route('admin.invoices.store'), [
                'direction' => 'purchase',
                'party_id' => $supplier->id,
                'invoice_no' => 'A-SAME-DAY',
                'issued_on' => $day,
                'is_draft' => false,
                'payment_status' => 'unpaid',
                'lines' => [[
                    'description' => $item->name,
                    'quantity' => '8',
                    'unit_price' => '25',
                    'vat_rate' => '0',
                    'stock_item_id' => $item->id,
                ]],
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->admin)
            ->post(route('admin.invoices.store'), [
                'direction' => 'sale',
                'party_id' => $customer->id,
                'invoice_no' => 'S-SAME-DAY',
                'issued_on' => $day,
                'is_draft' => false,
                'payment_status' => 'unpaid',
                'lines' => [[
                    'description' => $item->name,
                    'quantity' => '3',
                    'unit_price' => '40',
                    'vat_rate' => '0',
                    'stock_item_id' => $item->id,
                ]],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('25.0000', Invoice::query()->where('invoice_no', 'S-SAME-DAY')->sole()->lines()->sole()->unit_cost);
        $this->assertSame(
            ['quantity' => 5.0, 'average_cost' => 25.0, 'value' => 125.0],
            app(StockService::class)->stock($item),
        );
    }
}
