<?php

namespace Tests\Feature\Admin;

use App\Enums\CashDirection;
use App\Enums\StockItemUnit;
use App\Models\CashCategory;
use App\Models\StockItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockItemTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->yonetici()->create();
    }

    public function test_guest_is_redirected_from_the_stock_item_list(): void
    {
        $this->get(route('admin.stock-items.index'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_stock_item_list_and_menu_link_require_the_view_permission(): void
    {
        $viewer = User::factory()->create();
        $viewer->givePermissionTo(['view-dashboard', 'view-cash-category']);

        $this->actingAs($viewer)
            ->get(route('admin.stock-items.index'))
            ->assertForbidden();

        $this->actingAs($viewer)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee(route('admin.stock-items.index'));

        $viewer->givePermissionTo('view-stock-item');

        $this->actingAs($viewer->fresh())
            ->get(route('admin.dashboard'))
            ->assertSee(route('admin.stock-items.index'));
    }

    public function test_admin_can_create_update_and_delete_a_stock_item(): void
    {
        $category = CashCategory::factory()->create([
            'name' => 'Yedek parça',
            'direction' => CashDirection::Expense,
        ]);

        $this->actingAs($this->admin)->get(route('admin.stock-items.create'))->assertOk();

        $this->actingAs($this->admin)
            ->post(route('admin.stock-items.store'), [
                'name' => 'Filtre',
                'unit' => 'adet',
                'tracks_stock' => '1',
                'cash_category_id' => $category->id,
                'sales_price' => '125.5',
                'active' => '1',
            ])
            ->assertRedirect(route('admin.stock-items.index'))
            ->assertSessionHas('success');

        $item = StockItem::query()->where('name', 'Filtre')->firstOrFail();
        $this->assertSame(StockItemUnit::Piece, $item->unit);
        $this->assertTrue($item->tracks_stock);
        $this->assertSame($category->id, $item->cash_category_id);
        $this->assertSame('125.5000', $item->sales_price);

        $this->actingAs($this->admin)->get(route('admin.stock-items.show', $item))->assertOk();
        $this->actingAs($this->admin)->get(route('admin.stock-items.edit', $item))->assertOk();

        $this->actingAs($this->admin)
            ->put(route('admin.stock-items.update', $item), [
                'name' => 'Yağ filtresi',
                'unit' => 'lt',
                'tracks_stock' => '0',
                'cash_category_id' => $category->id,
                'sales_price' => '200',
            ])
            ->assertRedirect(route('admin.stock-items.index'));

        $item->refresh();
        $this->assertSame('Yağ filtresi', $item->name);
        $this->assertSame(StockItemUnit::Liter, $item->unit);
        $this->assertFalse($item->tracks_stock);
        $this->assertFalse($item->active);
        $this->assertSame('200.0000', $item->sales_price);

        $this->actingAs($this->admin)
            ->delete(route('admin.stock-items.destroy', $item))
            ->assertRedirect(route('admin.stock-items.index'));

        $this->assertSoftDeleted($item);
    }

    public function test_name_and_unit_are_required(): void
    {
        $this->actingAs($this->admin)
            ->from(route('admin.stock-items.create'))
            ->post(route('admin.stock-items.store'), ['name' => '', 'unit' => 'other'])
            ->assertRedirect(route('admin.stock-items.create'))
            ->assertSessionHasErrors(['name', 'unit']);

        $this->assertDatabaseCount('stock_items', 0);
    }

    public function test_name_must_be_unique_among_active_items(): void
    {
        $deleted = StockItem::factory()->create(['name' => 'Lastik']);
        $deleted->delete();
        StockItem::factory()->create(['name' => 'Balata']);

        $this->actingAs($this->admin)
            ->post(route('admin.stock-items.store'), [
                'name' => 'Balata',
                'unit' => 'adet',
                'sales_price' => '0',
            ])
            ->assertSessionHasErrors('name');

        $this->actingAs($this->admin)
            ->post(route('admin.stock-items.store'), [
                'name' => 'Lastik',
                'unit' => 'adet',
                'sales_price' => '0',
            ])
            ->assertSessionHasNoErrors();
    }

    public function test_default_category_must_be_expense_or_both(): void
    {
        $income = CashCategory::factory()->create([
            'name' => 'Faiz geliri',
            'direction' => CashDirection::Income,
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.stock-items.store'), [
                'name' => 'Filtre',
                'unit' => 'adet',
                'cash_category_id' => $income->id,
                'sales_price' => '10',
            ])
            ->assertSessionHasErrors('cash_category_id');

        $this->assertDatabaseCount('stock_items', 0);
    }

    public function test_list_searches_and_filters_by_unit_tracking_and_status(): void
    {
        StockItem::factory()->create(['name' => 'Mazot', 'unit' => StockItemUnit::Liter]);
        StockItem::factory()->create(['name' => 'Çimento', 'unit' => StockItemUnit::Kilogram, 'active' => false]);
        StockItem::factory()->create(['name' => 'Hizmet bedeli', 'unit' => StockItemUnit::Piece, 'tracks_stock' => false]);

        $this->actingAs($this->admin)
            ->get(route('admin.stock-items.index', ['q' => 'maz']))
            ->assertOk()
            ->assertSee('<td>Mazot</td>', false)
            ->assertDontSee('<td>Çimento</td>', false);

        $this->actingAs($this->admin)
            ->get(route('admin.stock-items.index', ['active' => '0']))
            ->assertSee('<td>Çimento</td>', false)
            ->assertDontSee('<td>Mazot</td>', false);

        $this->actingAs($this->admin)
            ->get(route('admin.stock-items.index', ['unit' => 'lt']))
            ->assertSee('<td>Mazot</td>', false)
            ->assertDontSee('<td>Hizmet bedeli</td>', false);

        $this->actingAs($this->admin)
            ->get(route('admin.stock-items.index', ['tracks_stock' => '0']))
            ->assertSee('<td>Hizmet bedeli</td>', false)
            ->assertDontSee('<td>Mazot</td>', false);
    }
}
