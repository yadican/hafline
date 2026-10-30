<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\StockItem;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class StockServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchase_lines_build_quantity_and_weighted_average_cost(): void
    {
        $item = StockItem::factory()->create(['name' => 'Çimento']);

        $this->purchase($item, '2026-03-01', '100', '40');
        $this->purchase($item, '2026-03-05', '100', '46');

        $stock = app(StockService::class)->stock($item);

        $this->assertSame(['quantity' => 200.0, 'average_cost' => 43.0, 'value' => 8600.0], $stock);
        $this->assertSame(
            ['quantity' => 100.0, 'average_cost' => 40.0, 'value' => 4000.0],
            app(StockService::class)->stock($item, Carbon::parse('2026-03-01')),
        );
    }

    public function test_draft_and_untracked_lines_do_not_move_stock(): void
    {
        $tracked = StockItem::factory()->create();
        $untracked = StockItem::factory()->untracked()->create();

        $draftPurchase = Invoice::factory()->draft()->create(['issued_on' => '2026-03-01', 'subtotal' => 400]);
        $draftPurchase->lines()->update(['stock_item_id' => $tracked->id, 'quantity' => 10, 'unit_price' => 40, 'subtotal' => 400, 'vat_amount' => 0, 'total' => 400]);

        $draftSale = Invoice::factory()->sale()->draft()->create(['issued_on' => '2026-03-02', 'subtotal' => 500]);
        $draftSale->lines()->update(['stock_item_id' => $tracked->id, 'quantity' => 5, 'unit_price' => 100, 'subtotal' => 500, 'vat_amount' => 0, 'total' => 500]);

        $this->purchase($untracked, '2026-03-03', '20', '10');

        $stock = app(StockService::class);

        $this->assertSame(['quantity' => 0.0, 'average_cost' => 0.0, 'value' => 0.0], $stock->stock($tracked));
        $this->assertSame(['quantity' => 0.0, 'average_cost' => 0.0, 'value' => 0.0], $stock->stock($untracked));
    }

    public function test_sale_lines_reduce_quantity_at_running_average(): void
    {
        $item = StockItem::factory()->create();

        $this->purchase($item, '2026-03-01', '100', '40');
        $this->purchase($item, '2026-03-05', '100', '46');
        $this->sale($item, '2026-03-10', '50', '60', unitCost: 43);

        $this->assertSame(
            ['quantity' => 150.0, 'average_cost' => 43.0, 'value' => 6450.0],
            app(StockService::class)->stock($item),
        );
        $this->assertSame(
            ['quantity' => 200.0, 'average_cost' => 43.0, 'value' => 8600.0],
            app(StockService::class)->stock($item, Carbon::parse('2026-03-05')),
        );
    }

    public function test_unit_cost_uses_line_subtotal_not_vat_total(): void
    {
        $item = StockItem::factory()->create();
        $invoice = Invoice::factory()->vat(20)->create(['issued_on' => '2026-03-01', 'subtotal' => 1000, 'vat_amount' => 200]);
        $invoice->lines()->delete();
        InvoiceLine::factory()->vat(20)->create([
            'invoice_id' => $invoice->id,
            'stock_item_id' => $item->id,
            'quantity' => 10,
            'unit_price' => 100,
            'subtotal' => 1000,
            'vat_amount' => 200,
            'total' => 1200,
        ]);

        $this->assertSame(
            ['quantity' => 10.0, 'average_cost' => 100.0, 'value' => 1000.0],
            app(StockService::class)->stock($item),
        );
    }

    private function purchase(StockItem $item, string $date, string $quantity, string $unitPrice): void
    {
        $subtotal = round((float) $quantity * (float) $unitPrice, 2);
        $invoice = Invoice::factory()->create(['issued_on' => $date, 'subtotal' => $subtotal, 'vat_amount' => 0]);
        $invoice->lines()->delete();
        InvoiceLine::factory()->create([
            'invoice_id' => $invoice->id,
            'stock_item_id' => $item->id,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'subtotal' => $subtotal,
            'vat_amount' => 0,
            'total' => $subtotal,
        ]);
    }

    private function sale(StockItem $item, string $date, string $quantity, string $unitPrice, float $unitCost): void
    {
        $subtotal = round((float) $quantity * (float) $unitPrice, 2);
        $invoice = Invoice::factory()->sale()->create(['issued_on' => $date, 'subtotal' => $subtotal, 'vat_amount' => 0]);
        $invoice->lines()->delete();
        InvoiceLine::factory()->create([
            'invoice_id' => $invoice->id,
            'stock_item_id' => $item->id,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'subtotal' => $subtotal,
            'vat_amount' => 0,
            'total' => $subtotal,
            'unit_cost' => $unitCost,
        ]);
    }
}
