<?php

namespace Tests\Feature\Admin;

use App\Models\CashCategory;
use App\Models\Invoice;
use App\Models\Party;
use App\Models\Site;
use App\Models\StockItem;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoicePurchaseStockGuardTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Party $supplier;

    private Party $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->yonetici()->create();
        $this->supplier = Party::factory()->company()->create();
        $this->customer = Party::factory()->company()->create();
    }

    public function test_cannot_reduce_purchase_below_sold_quantity(): void
    {
        $item = StockItem::factory()->create(['name' => 'Çimento']);
        $this->purchase($item, 'A-USED-1', '2026-03-01', '10', '40');
        $this->sale($item, 'S-USED-1', '2026-03-10', '6', '60');

        $invoice = Invoice::query()->where('invoice_no', 'A-USED-1')->sole();
        $payload = $this->purchasePayload('A-USED-1', '2026-03-01', $item, '5', '40');
        unset($payload['is_draft'], $payload['payment_status']);

        $this->actingAs($this->admin)
            ->from(route('admin.invoices.edit', $invoice))
            ->put(route('admin.invoices.update', $invoice), $payload)
            ->assertRedirect(route('admin.invoices.edit', $invoice))
            ->assertSessionHas('error', 'Çimento alış miktarı kullanılmış stokun altına düşürülemez (kullanılan 6 adet).');

        $this->assertSame('10.000', $invoice->fresh()->lines()->sole()->quantity);
        $this->assertSame(
            ['quantity' => 4.0, 'average_cost' => 40.0, 'value' => 160.0],
            app(StockService::class)->stock($item),
        );
    }

    public function test_can_reduce_purchase_down_to_used_quantity(): void
    {
        $item = StockItem::factory()->create(['name' => 'Demir']);
        $this->purchase($item, 'A-FLOOR-1', '2026-03-01', '10', '50');
        $this->sale($item, 'S-FLOOR-1', '2026-03-10', '6', '70');

        $invoice = Invoice::query()->where('invoice_no', 'A-FLOOR-1')->sole();
        $payload = $this->purchasePayload('A-FLOOR-1', '2026-03-01', $item, '6', '50');
        unset($payload['is_draft'], $payload['payment_status']);

        $this->actingAs($this->admin)
            ->put(route('admin.invoices.update', $invoice), $payload)
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $this->assertSame('6.000', $invoice->fresh()->lines()->sole()->quantity);
        $stock = app(StockService::class)->stock($item);
        $this->assertSame(0.0, $stock['quantity']);
        $this->assertSame(0.0, $stock['value']);
    }

    public function test_cannot_delete_purchase_that_fed_a_sale(): void
    {
        $item = StockItem::factory()->create(['name' => 'Kum']);
        $this->purchase($item, 'A-DEL-1', '2026-03-01', '10', '30');
        $this->sale($item, 'S-DEL-1', '2026-03-05', '4', '45');

        $invoice = Invoice::query()->where('invoice_no', 'A-DEL-1')->sole();

        $this->actingAs($this->admin)
            ->from(route('admin.invoices.show', $invoice))
            ->delete(route('admin.invoices.destroy', $invoice))
            ->assertRedirect(route('admin.invoices.show', $invoice))
            ->assertSessionHas('error', 'Stok çıkışı olan alış faturası silinemez.');

        $this->assertNotSoftDeleted($invoice);
        $this->assertSame(
            ['quantity' => 6.0, 'average_cost' => 30.0, 'value' => 180.0],
            app(StockService::class)->stock($item),
        );
    }

    public function test_can_delete_unused_purchase(): void
    {
        $item = StockItem::factory()->create();
        $this->purchase($item, 'A-FREE-1', '2026-03-01', '8', '25');

        $invoice = Invoice::query()->where('invoice_no', 'A-FREE-1')->sole();

        $this->actingAs($this->admin)
            ->delete(route('admin.invoices.destroy', $invoice))
            ->assertRedirect(route('admin.invoices.index'))
            ->assertSessionHas('success');

        $this->assertSoftDeleted($invoice);
        $this->assertSame(
            ['quantity' => 0.0, 'average_cost' => 0.0, 'value' => 0.0],
            app(StockService::class)->stock($item),
        );
    }

    public function test_usage_counts_toward_purchase_floor(): void
    {
        $item = StockItem::factory()->create(['name' => 'Filtre']);
        $site = Site::factory()->create();
        $category = CashCategory::factory()->create();
        $this->purchase($item, 'A-USE-1', '2026-03-01', '10', '40');

        $this->actingAs($this->admin)
            ->post(route('admin.stock-usages.store'), [
                'stock_item_id' => $item->id,
                'quantity' => '7',
                'used_on' => '2026-03-08',
                'site_id' => $site->id,
                'cash_category_id' => $category->id,
            ])
            ->assertSessionHasNoErrors();

        $invoice = Invoice::query()->where('invoice_no', 'A-USE-1')->sole();
        $payload = $this->purchasePayload('A-USE-1', '2026-03-01', $item, '6', '40');
        unset($payload['is_draft'], $payload['payment_status']);

        $this->actingAs($this->admin)
            ->from(route('admin.invoices.edit', $invoice))
            ->put(route('admin.invoices.update', $invoice), $payload)
            ->assertRedirect(route('admin.invoices.edit', $invoice))
            ->assertSessionHas('error', 'Filtre alış miktarı kullanılmış stokun altına düşürülemez (kullanılan 7 adet).');
    }

    public function test_sale_return_frees_purchase_floor_after_peak_exit(): void
    {
        $item = StockItem::factory()->create(['name' => 'Çakıl']);
        $this->purchase($item, 'A-RET-1', '2026-03-01', '10', '20');
        $this->sale($item, 'S-RET-1', '2026-03-05', '6', '35');

        $sale = Invoice::query()->where('invoice_no', 'S-RET-1')->sole();

        $this->actingAs($this->admin)
            ->post(route('admin.invoices.store'), [
                'direction' => 'sale_return',
                'party_id' => $this->customer->id,
                'invoice_no' => 'SI-RET-1',
                'issued_on' => '2026-03-12',
                'is_draft' => false,
                'returns_invoice_id' => $sale->id,
                'lines' => [[
                    'description' => $item->name,
                    'quantity' => '2',
                    'unit_price' => '35',
                    'vat_rate' => '0',
                    'stock_item_id' => $item->id,
                ]],
            ])
            ->assertSessionHasNoErrors();

        $invoice = Invoice::query()->where('invoice_no', 'A-RET-1')->sole();

        $ok = $this->purchasePayload('A-RET-1', '2026-03-01', $item, '6', '20');
        unset($ok['is_draft'], $ok['payment_status']);

        $this->actingAs($this->admin)
            ->put(route('admin.invoices.update', $invoice), $ok)
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $tooLow = $this->purchasePayload('A-RET-1', '2026-03-01', $item, '5', '20');
        unset($tooLow['is_draft'], $tooLow['payment_status']);

        $this->actingAs($this->admin)
            ->from(route('admin.invoices.edit', $invoice))
            ->put(route('admin.invoices.update', $invoice), $tooLow)
            ->assertRedirect(route('admin.invoices.edit', $invoice))
            ->assertSessionHas('error', 'Çakıl alış miktarı kullanılmış stokun altına düşürülemez (kullanılan 6 adet).');

        $this->assertSame(
            ['quantity' => 2.0, 'average_cost' => 20.0, 'value' => 40.0],
            app(StockService::class)->stock($item),
        );
    }

    public function test_covering_second_purchase_allows_deleting_unused_first(): void
    {
        $item = StockItem::factory()->create();
        $this->purchase($item, 'A-COVER-1', '2026-03-01', '10', '40');
        $this->purchase($item, 'A-COVER-2', '2026-03-02', '10', '40');
        $this->sale($item, 'S-COVER-1', '2026-03-10', '5', '55');

        $first = Invoice::query()->where('invoice_no', 'A-COVER-1')->sole();

        $this->actingAs($this->admin)
            ->delete(route('admin.invoices.destroy', $first))
            ->assertRedirect(route('admin.invoices.index'))
            ->assertSessionHas('success');

        $this->assertSoftDeleted($first);
        $this->assertSame(
            ['quantity' => 5.0, 'average_cost' => 40.0, 'value' => 200.0],
            app(StockService::class)->stock($item),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function purchasePayload(string $no, string $date, StockItem $item, string $quantity, string $unitPrice): array
    {
        return [
            'direction' => 'purchase',
            'party_id' => $this->supplier->id,
            'invoice_no' => $no,
            'issued_on' => $date,
            'is_draft' => false,
            'payment_status' => 'unpaid',
            'lines' => [[
                'description' => $item->name,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'vat_rate' => '0',
                'stock_item_id' => $item->id,
            ]],
        ];
    }

    private function purchase(StockItem $item, string $no, string $date, string $quantity, string $unitPrice): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.invoices.store'), $this->purchasePayload($no, $date, $item, $quantity, $unitPrice))
            ->assertSessionHasNoErrors();
    }

    private function sale(StockItem $item, string $no, string $date, string $quantity, string $unitPrice): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.invoices.store'), [
                'direction' => 'sale',
                'party_id' => $this->customer->id,
                'invoice_no' => $no,
                'issued_on' => $date,
                'is_draft' => false,
                'payment_status' => 'unpaid',
                'lines' => [[
                    'description' => $item->name,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'vat_rate' => '0',
                    'stock_item_id' => $item->id,
                ]],
            ])
            ->assertSessionHasNoErrors();
    }
}
