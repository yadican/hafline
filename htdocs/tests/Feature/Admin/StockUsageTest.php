<?php

namespace Tests\Feature\Admin;

use App\Models\CashCategory;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Party;
use App\Models\Site;
use App\Models\StockItem;
use App\Models\StockUsage;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockUsageTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->yonetici()->create();
    }

    public function test_guest_is_redirected_from_stock_usage_list(): void
    {
        $this->get(route('admin.stock-usages.index'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_stock_usage_menu_requires_the_view_permission(): void
    {
        $viewer = User::factory()->create();
        $viewer->givePermissionTo(['view-dashboard', 'view-stock-item']);

        $this->actingAs($viewer)
            ->get(route('admin.stock-usages.index'))
            ->assertForbidden();

        $this->actingAs($viewer)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee(route('admin.stock-usages.index'));

        $viewer->givePermissionTo('view-stock-usage');

        $this->actingAs($viewer->fresh())
            ->get(route('admin.dashboard'))
            ->assertSee(route('admin.stock-usages.index'));
    }

    public function test_usage_drops_stock_at_average_cost_and_freezes_unit_cost(): void
    {
        $item = StockItem::factory()->create(['name' => 'Filtre']);
        $site = Site::factory()->create();
        $category = CashCategory::factory()->create(['name' => 'Şantiye sarf']);

        $this->purchase($item, '2026-03-01', '100', '40');
        $this->purchase($item, '2026-03-05', '100', '46');

        $this->actingAs($this->admin)
            ->post(route('admin.stock-usages.store'), [
                'stock_item_id' => $item->id,
                'quantity' => '50',
                'used_on' => '2026-03-10',
                'site_id' => $site->id,
                'cash_category_id' => $category->id,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.stock-usages.index'));

        $usage = StockUsage::query()->sole();
        $this->assertSame('43.0000', $usage->unit_cost);
        $this->assertSame('2150.00', $usage->amount);
        $this->assertSame($site->id, $usage->site_id);
        $this->assertNull($usage->vehicle_id);
        $this->assertSame(
            ['quantity' => 150.0, 'average_cost' => 43.0, 'value' => 6450.0],
            app(StockService::class)->stock($item),
        );

        $this->actingAs($this->admin)
            ->get(route('admin.stock-items.show', $item))
            ->assertOk()
            ->assertSee('Stok kullanımı')
            ->assertSee(route('admin.stock-usages.show', $usage));
    }

    public function test_usage_on_own_vehicle_without_site_is_allowed(): void
    {
        $item = StockItem::factory()->create();
        $vehicle = Vehicle::factory()->create();
        $category = CashCategory::factory()->create();

        $this->purchase($item, '2026-04-01', '10', '25');

        $this->actingAs($this->admin)
            ->post(route('admin.stock-usages.store'), [
                'stock_item_id' => $item->id,
                'quantity' => '2',
                'used_on' => '2026-04-02',
                'vehicle_id' => $vehicle->id,
                'cash_category_id' => $category->id,
            ])
            ->assertSessionHasNoErrors();

        $usage = StockUsage::query()->sole();
        $this->assertSame($vehicle->id, $usage->vehicle_id);
        $this->assertNull($usage->site_id);
        $this->assertSame(
            ['quantity' => 8.0, 'average_cost' => 25.0, 'value' => 200.0],
            app(StockService::class)->stock($item),
        );
    }

    public function test_usage_rejects_when_stock_would_go_negative(): void
    {
        $item = StockItem::factory()->create(['name' => 'Lastik']);
        $site = Site::factory()->create();
        $category = CashCategory::factory()->create();

        $this->purchase($item, '2026-05-01', '5', '100');

        $this->actingAs($this->admin)
            ->from(route('admin.stock-usages.create'))
            ->post(route('admin.stock-usages.store'), [
                'stock_item_id' => $item->id,
                'quantity' => '6',
                'used_on' => '2026-05-02',
                'site_id' => $site->id,
                'cash_category_id' => $category->id,
            ])
            ->assertRedirect(route('admin.stock-usages.create'))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('stock_usages', 0);
        $this->assertSame(
            ['quantity' => 5.0, 'average_cost' => 100.0, 'value' => 500.0],
            app(StockService::class)->stock($item),
        );
    }

    public function test_site_or_vehicle_is_required(): void
    {
        $item = StockItem::factory()->create();
        $category = CashCategory::factory()->create();
        $this->purchase($item, '2026-06-01', '10', '10');

        $this->actingAs($this->admin)
            ->from(route('admin.stock-usages.create'))
            ->post(route('admin.stock-usages.store'), [
                'stock_item_id' => $item->id,
                'quantity' => '1',
                'used_on' => '2026-06-02',
                'cash_category_id' => $category->id,
            ])
            ->assertRedirect(route('admin.stock-usages.create'))
            ->assertSessionHasErrors(['site_id', 'vehicle_id']);
    }

    public function test_admin_can_update_and_delete_a_usage(): void
    {
        $item = StockItem::factory()->create();
        $site = Site::factory()->create();
        $otherSite = Site::factory()->create();
        $category = CashCategory::factory()->create();
        $this->purchase($item, '2026-07-01', '20', '50');

        $this->actingAs($this->admin)
            ->post(route('admin.stock-usages.store'), [
                'stock_item_id' => $item->id,
                'quantity' => '4',
                'used_on' => '2026-07-02',
                'site_id' => $site->id,
                'cash_category_id' => $category->id,
            ])
            ->assertSessionHasNoErrors();

        $usage = StockUsage::query()->sole();

        $this->actingAs($this->admin)
            ->put(route('admin.stock-usages.update', $usage), [
                'stock_item_id' => $item->id,
                'quantity' => '6',
                'used_on' => '2026-07-03',
                'site_id' => $otherSite->id,
                'cash_category_id' => $category->id,
                'description' => 'Şantiye sarfiyatı',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.stock-usages.index'));

        $usage->refresh();
        $this->assertSame('6.000', $usage->quantity);
        $this->assertSame($otherSite->id, $usage->site_id);
        $this->assertSame('Şantiye sarfiyatı', $usage->description);
        $this->assertSame(
            ['quantity' => 14.0, 'average_cost' => 50.0, 'value' => 700.0],
            app(StockService::class)->stock($item),
        );

        $this->actingAs($this->admin)
            ->delete(route('admin.stock-usages.destroy', $usage))
            ->assertRedirect(route('admin.stock-usages.index'));

        $this->assertSoftDeleted($usage);
        $this->assertSame(
            ['quantity' => 20.0, 'average_cost' => 50.0, 'value' => 1000.0],
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
}
