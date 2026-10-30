<?php

namespace Tests\Feature\Admin;

use App\Models\Invoice;
use App\Models\Party;
use App\Models\StockItem;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceStockTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->yonetici()->create();
    }

    public function test_create_form_offers_stock_items_and_purchase_enters_stock(): void
    {
        $item = StockItem::factory()->create(['name' => 'Çimento 42.5']);
        $supplier = Party::factory()->company()->create();

        $this->actingAs($this->admin)
            ->get(route('admin.invoices.create'))
            ->assertOk()
            ->assertViewHas('options', fn (array $options): bool => array_key_exists($item->id, $options['stock_items'])
                && ($options['stock_item_meta'][$item->id]['tracks_stock'] ?? false) === true);

        $this->actingAs($this->admin)
            ->post(route('admin.invoices.store'), [
                'direction' => 'purchase',
                'party_id' => $supplier->id,
                'invoice_no' => 'A-STOCK-1',
                'issued_on' => today()->toDateString(),
                'is_draft' => false,
                'payment_status' => 'unpaid',
                'lines' => [
                    [
                        'description' => 'Çimento 42.5',
                        'quantity' => '10',
                        'unit_price' => '250',
                        'vat_rate' => '20',
                        'stock_item_id' => $item->id,
                    ],
                    [
                        'description' => 'Nakliye',
                        'quantity' => '1',
                        'unit_price' => '500',
                        'vat_rate' => '0',
                    ],
                ],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.invoices.index'));

        $invoice = Invoice::query()->sole();
        $this->assertSame($item->id, $invoice->lines()->where('description', 'Çimento 42.5')->sole()->stock_item_id);
        $this->assertNull($invoice->lines()->where('description', 'Nakliye')->sole()->stock_item_id);
        $this->assertSame(
            ['quantity' => 10.0, 'average_cost' => 250.0, 'value' => 2500.0],
            app(StockService::class)->stock($item),
        );
    }

    public function test_draft_purchase_does_not_enter_stock_until_approved(): void
    {
        $item = StockItem::factory()->create();
        $supplier = Party::factory()->company()->create();

        $this->actingAs($this->admin)
            ->post(route('admin.invoices.store'), [
                'direction' => 'purchase',
                'party_id' => $supplier->id,
                'invoice_no' => 'A-DRAFT-1',
                'issued_on' => today()->toDateString(),
                'is_draft' => true,
                'lines' => [[
                    'description' => 'Kalem',
                    'quantity' => '5',
                    'unit_price' => '100',
                    'vat_rate' => '0',
                    'stock_item_id' => $item->id,
                ]],
            ])
            ->assertSessionHasNoErrors();

        $invoice = Invoice::query()->sole();
        $this->assertTrue($invoice->is_draft);
        $this->assertSame($item->id, $invoice->lines()->sole()->stock_item_id);
        $this->assertSame(['quantity' => 0.0, 'average_cost' => 0.0, 'value' => 0.0], app(StockService::class)->stock($item));

        $this->actingAs($this->admin)
            ->post(route('admin.invoices.approve', $invoice))
            ->assertSessionHas('success');

        $this->assertSame(
            ['quantity' => 5.0, 'average_cost' => 100.0, 'value' => 500.0],
            app(StockService::class)->stock($item),
        );
    }

    public function test_two_purchases_produce_weighted_average_cost(): void
    {
        $item = StockItem::factory()->create();
        $supplier = Party::factory()->company()->create();

        foreach ([['A-1', '100', '40'], ['A-2', '100', '46']] as [$no, $qty, $price]) {
            $this->actingAs($this->admin)
                ->post(route('admin.invoices.store'), [
                    'direction' => 'purchase',
                    'party_id' => $supplier->id,
                    'invoice_no' => $no,
                    'issued_on' => today()->toDateString(),
                    'is_draft' => false,
                    'payment_status' => 'unpaid',
                    'lines' => [[
                        'description' => $item->name,
                        'quantity' => $qty,
                        'unit_price' => $price,
                        'vat_rate' => '0',
                        'stock_item_id' => $item->id,
                    ]],
                ])
                ->assertSessionHasNoErrors();
        }

        $this->assertSame(
            ['quantity' => 200.0, 'average_cost' => 43.0, 'value' => 8600.0],
            app(StockService::class)->stock($item),
        );
    }
}
