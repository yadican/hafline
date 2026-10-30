<?php

namespace Tests\Feature\Admin;

use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\LedgerEntry;
use App\Models\Party;
use App\Models\StockCount;
use App\Models\StockItem;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockCountTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->yonetici()->create();
    }

    public function test_guest_is_redirected_from_stock_count_list(): void
    {
        $this->get(route('admin.stock-counts.index'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_stock_count_menu_requires_the_view_permission(): void
    {
        $viewer = User::factory()->create();
        $viewer->givePermissionTo(['view-dashboard', 'view-stock-item']);

        $this->actingAs($viewer)
            ->get(route('admin.stock-counts.index'))
            ->assertForbidden();

        $this->actingAs($viewer)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee(route('admin.stock-counts.index'));

        $viewer->givePermissionTo('view-stock-count');

        $this->actingAs($viewer->fresh())
            ->get(route('admin.dashboard'))
            ->assertSee(route('admin.stock-counts.index'));
    }

    public function test_upward_count_difference_raises_stock_at_average_cost_without_cari(): void
    {
        $item = StockItem::factory()->create(['name' => 'Cıvata']);
        $this->purchase($item, '2026-03-01', '100', '40');
        $this->purchase($item, '2026-03-05', '100', '46');

        $ledgerBefore = LedgerEntry::query()->count();

        $this->actingAs($this->admin)
            ->post(route('admin.stock-counts.store'), [
                'stock_item_id' => $item->id,
                'quantity' => '220',
                'counted_on' => '2026-03-10',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.stock-counts.index'));

        $count = StockCount::query()->sole();
        $this->assertSame('220.000', $count->quantity);
        $this->assertSame('20.000', $count->difference);
        $this->assertSame($ledgerBefore, LedgerEntry::query()->count());
        $this->assertSame(
            ['quantity' => 220.0, 'average_cost' => 43.0, 'value' => 9460.0],
            app(StockService::class)->stock($item),
        );

        $this->actingAs($this->admin)
            ->get(route('admin.stock-items.show', $item))
            ->assertOk()
            ->assertSee('Sayım')
            ->assertSee(route('admin.stock-counts.show', $count));
    }

    public function test_downward_count_difference_lowers_stock_like_usage(): void
    {
        $item = StockItem::factory()->create(['name' => 'Filtre']);
        $this->purchase($item, '2026-04-01', '50', '30');

        $this->actingAs($this->admin)
            ->post(route('admin.stock-counts.store'), [
                'stock_item_id' => $item->id,
                'quantity' => '35',
                'counted_on' => '2026-04-02',
            ])
            ->assertSessionHasNoErrors();

        $count = StockCount::query()->sole();
        $this->assertSame('35.000', $count->quantity);
        $this->assertSame('-15.000', $count->difference);
        $this->assertSame(
            ['quantity' => 35.0, 'average_cost' => 30.0, 'value' => 1050.0],
            app(StockService::class)->stock($item),
        );
        $this->assertSame(0, LedgerEntry::query()->where('description', 'like', '%Sayım%')->count());
    }

    public function test_count_on_empty_stock_enters_at_zero_average(): void
    {
        $item = StockItem::factory()->create();

        $this->actingAs($this->admin)
            ->post(route('admin.stock-counts.store'), [
                'stock_item_id' => $item->id,
                'quantity' => '10',
                'counted_on' => '2026-05-01',
            ])
            ->assertSessionHasNoErrors();

        $count = StockCount::query()->sole();
        $this->assertSame('10.000', $count->difference);
        $this->assertSame(
            ['quantity' => 10.0, 'average_cost' => 0.0, 'value' => 0.0],
            app(StockService::class)->stock($item),
        );
    }

    public function test_count_to_zero_after_purchase_is_allowed(): void
    {
        $item = StockItem::factory()->create(['name' => 'Lastik']);
        $this->purchase($item, '2026-06-01', '5', '100');

        $this->actingAs($this->admin)
            ->post(route('admin.stock-counts.store'), [
                'stock_item_id' => $item->id,
                'quantity' => '0',
                'counted_on' => '2026-06-02',
            ])
            ->assertSessionHasNoErrors();

        $count = StockCount::query()->sole();
        $this->assertSame('-5.000', $count->difference);
        $this->assertSame(
            ['quantity' => 0.0, 'average_cost' => 100.0, 'value' => 0.0],
            app(StockService::class)->stock($item),
        );
    }

    public function test_downward_count_rejects_when_later_movements_would_go_negative(): void
    {
        $item = StockItem::factory()->create(['name' => 'Yağ']);
        $this->purchase($item, '2026-07-01', '20', '50');
        $this->sale($item, '2026-07-20', '10', '60', '50');

        // Book after purchase+sale = 10. Counting to 5 on the 10th leaves the later sale short.
        $this->actingAs($this->admin)
            ->from(route('admin.stock-counts.create'))
            ->post(route('admin.stock-counts.store'), [
                'stock_item_id' => $item->id,
                'quantity' => '5',
                'counted_on' => '2026-07-10',
            ])
            ->assertRedirect(route('admin.stock-counts.create'))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('stock_counts', 0);
    }

    public function test_admin_can_update_and_delete_a_count(): void
    {
        $item = StockItem::factory()->create();
        $this->purchase($item, '2026-08-01', '40', '25');

        $this->actingAs($this->admin)
            ->post(route('admin.stock-counts.store'), [
                'stock_item_id' => $item->id,
                'quantity' => '45',
                'counted_on' => '2026-08-02',
            ])
            ->assertSessionHasNoErrors();

        $count = StockCount::query()->sole();
        $this->assertSame('5.000', $count->difference);

        $this->actingAs($this->admin)
            ->put(route('admin.stock-counts.update', $count), [
                'stock_item_id' => $item->id,
                'quantity' => '30',
                'counted_on' => '2026-08-03',
                'description' => 'Depo sayımı',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.stock-counts.index'));

        $count->refresh();
        $this->assertSame('30.000', $count->quantity);
        $this->assertSame('-10.000', $count->difference);
        $this->assertSame('Depo sayımı', $count->description);
        $this->assertSame(
            ['quantity' => 30.0, 'average_cost' => 25.0, 'value' => 750.0],
            app(StockService::class)->stock($item),
        );

        $this->actingAs($this->admin)
            ->delete(route('admin.stock-counts.destroy', $count))
            ->assertRedirect(route('admin.stock-counts.index'));

        $this->assertSoftDeleted($count);
        $this->assertSame(
            ['quantity' => 40.0, 'average_cost' => 25.0, 'value' => 1000.0],
            app(StockService::class)->stock($item),
        );
    }

    private function purchase(StockItem $item, string $date, string $quantity, string $unitPrice): void
    {
        $subtotal = round((float) $quantity * (float) $unitPrice, 2);
        $invoice = Invoice::factory()->create([
            'party_id' => Party::factory()->company(),
            'issued_on' => $date,
            'subtotal' => $subtotal,
            'vat_amount' => 0,
            'is_draft' => false,
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
    }

    private function sale(StockItem $item, string $date, string $quantity, string $unitPrice, string $unitCost): void
    {
        $subtotal = round((float) $quantity * (float) $unitPrice, 2);
        $invoice = Invoice::factory()->sale()->create([
            'party_id' => Party::factory()->company(),
            'issued_on' => $date,
            'subtotal' => $subtotal,
            'vat_amount' => 0,
            'is_draft' => false,
        ]);
        $invoice->lines()->delete();
        InvoiceLine::factory()->create([
            'invoice_id' => $invoice->id,
            'stock_item_id' => $item->id,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'unit_cost' => $unitCost,
            'subtotal' => $subtotal,
            'vat_amount' => 0,
            'total' => $subtotal,
        ]);
    }
}
