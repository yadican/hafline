<?php

namespace Tests\Feature\Admin;

use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\StockItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockItemDetailTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->yonetici()->create();
    }

    public function test_detail_shows_remaining_average_cost_value_and_purchase_movements(): void
    {
        $item = StockItem::factory()->create(['name' => 'Çimento 42.5', 'unit' => 'adet']);

        $first = $this->purchase($item, '2026-03-01', 'A-100', '100', '40');
        $second = $this->purchase($item, '2026-03-05', 'A-101', '100', '46');

        $this->actingAs($this->admin)
            ->get(route('admin.stock-items.show', $item))
            ->assertOk()
            ->assertSee('Çimento 42.5')
            ->assertSeeInOrder(['Kalan', '200 adet'])
            ->assertSeeInOrder(['Ortalama maliyet', '43,0000 TL/adet'])
            ->assertSeeInOrder(['Stok değeri', '8.600,00 TL'])
            ->assertSeeInOrder(['Hareketler', 'Giriş'])
            ->assertSee('Alış faturası A-101')
            ->assertSee('Alış faturası A-100')
            ->assertSee(route('admin.invoices.show', $first), false)
            ->assertSee(route('admin.invoices.show', $second), false)
            ->assertSeeInOrder(['05.03.2026', 'Giriş', '+100 adet', '46,0000', '4.600,00'])
            ->assertDontSee('Henüz stok hareketi yok.');
    }

    public function test_detail_hides_stock_blocks_when_item_does_not_track_stock(): void
    {
        $item = StockItem::factory()->untracked()->create(['name' => 'Hizmet bedeli']);

        $this->actingAs($this->admin)
            ->get(route('admin.stock-items.show', $item))
            ->assertOk()
            ->assertSee('Hizmet bedeli')
            ->assertDontSee('Stok değeri')
            ->assertDontSee('Hareketler')
            ->assertDontSee('Henüz stok hareketi yok.');
    }

    public function test_detail_shows_empty_movements_hint_when_tracked_but_unused(): void
    {
        $item = StockItem::factory()->create(['name' => 'Filtre']);

        $this->actingAs($this->admin)
            ->get(route('admin.stock-items.show', $item))
            ->assertOk()
            ->assertSeeInOrder(['Kalan', '0 adet'])
            ->assertSee('Henüz stok hareketi yok.');
    }

    public function test_draft_and_soft_deleted_purchases_do_not_appear_as_movements(): void
    {
        $item = StockItem::factory()->create();
        $live = $this->purchase($item, '2026-03-01', 'A-LIVE', '10', '50');

        $draft = Invoice::factory()->draft()->create([
            'invoice_no' => 'A-DRAFT',
            'issued_on' => '2026-03-02',
            'subtotal' => 500,
            'vat_amount' => 0,
        ]);
        $draft->lines()->delete();
        InvoiceLine::factory()->create([
            'invoice_id' => $draft->id,
            'stock_item_id' => $item->id,
            'quantity' => 10,
            'unit_price' => 50,
            'subtotal' => 500,
            'vat_amount' => 0,
            'total' => 500,
        ]);

        $deleted = $this->purchase($item, '2026-03-03', 'A-GONE', '5', '50');
        $deleted->delete();

        $this->actingAs($this->admin)
            ->get(route('admin.stock-items.show', $item))
            ->assertOk()
            ->assertSeeInOrder(['Kalan', '10 adet'])
            ->assertSee('Alış faturası A-LIVE')
            ->assertSee(route('admin.invoices.show', $live), false)
            ->assertDontSee('A-DRAFT')
            ->assertDontSee('A-GONE');
    }

    private function purchase(StockItem $item, string $date, string $no, string $quantity, string $unitPrice): Invoice
    {
        $subtotal = round((float) $quantity * (float) $unitPrice, 2);
        $invoice = Invoice::factory()->create([
            'invoice_no' => $no,
            'issued_on' => $date,
            'subtotal' => $subtotal,
            'vat_amount' => 0,
        ]);
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

        return $invoice;
    }
}
