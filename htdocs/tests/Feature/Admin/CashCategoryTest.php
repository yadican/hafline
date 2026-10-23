<?php

namespace Tests\Feature\Admin;

use App\Enums\CashDirection;
use App\Models\CashCategory;
use App\Models\User;
use App\Repositories\CashCategoryRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashCategoryTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->yonetici()->create();
    }

    public function test_guest_is_redirected_from_the_cash_category_list(): void
    {
        $this->get(route('admin.cash-categories.index'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_admin_can_create_update_and_delete_a_cash_category(): void
    {
        $this->actingAs($this->admin)->get(route('admin.cash-categories.create'))->assertOk();

        $this->actingAs($this->admin)
            ->post(route('admin.cash-categories.store'), ['name' => 'Yemek', 'direction' => 'expense', 'active' => '1'])
            ->assertRedirect(route('admin.cash-categories.index'))
            ->assertSessionHas('success');

        $category = CashCategory::query()->where('name', 'Yemek')->firstOrFail();
        $this->assertSame(CashDirection::Expense, $category->direction);

        $this->actingAs($this->admin)->get(route('admin.cash-categories.show', $category))->assertOk();
        $this->actingAs($this->admin)->get(route('admin.cash-categories.edit', $category))->assertOk();

        $this->actingAs($this->admin)
            ->put(route('admin.cash-categories.update', $category), ['name' => 'Yemek giderleri', 'direction' => 'both'])
            ->assertRedirect(route('admin.cash-categories.index'));

        $category->refresh();
        $this->assertSame('Yemek giderleri', $category->name);
        $this->assertSame(CashDirection::Both, $category->direction);
        $this->assertFalse($category->active);

        $this->actingAs($this->admin)
            ->delete(route('admin.cash-categories.destroy', $category))
            ->assertRedirect(route('admin.cash-categories.index'));

        $this->assertSoftDeleted($category);
    }

    public function test_name_and_direction_are_required(): void
    {
        $this->actingAs($this->admin)
            ->from(route('admin.cash-categories.create'))
            ->post(route('admin.cash-categories.store'), ['name' => '', 'direction' => 'other'])
            ->assertRedirect(route('admin.cash-categories.create'))
            ->assertSessionHasErrors(['name', 'direction']);

        $this->assertDatabaseCount('cash_categories', 0);
    }

    public function test_name_must_be_unique_among_active_categories(): void
    {
        $deleted = CashCategory::factory()->create(['name' => 'Lastik']);
        $deleted->delete();
        CashCategory::factory()->create(['name' => 'Sigorta']);

        $this->actingAs($this->admin)
            ->post(route('admin.cash-categories.store'), ['name' => 'Sigorta', 'direction' => 'expense'])
            ->assertSessionHasErrors('name');

        $this->actingAs($this->admin)
            ->post(route('admin.cash-categories.store'), ['name' => 'Lastik', 'direction' => 'expense'])
            ->assertSessionHasNoErrors();
    }

    public function test_list_searches_and_filters_by_direction_and_status(): void
    {
        CashCategory::factory()->create(['name' => 'Maaş']);
        CashCategory::factory()->create(['name' => 'Kira', 'active' => false]);
        CashCategory::factory()->create(['name' => 'Faiz geliri', 'direction' => CashDirection::Income]);

        $this->actingAs($this->admin)
            ->get(route('admin.cash-categories.index', ['q' => 'maa']))
            ->assertOk()
            ->assertSee('<td>Maaş</td>', false)
            ->assertDontSee('<td>Kira</td>', false);

        $this->actingAs($this->admin)
            ->get(route('admin.cash-categories.index', ['active' => '0']))
            ->assertSee('<td>Kira</td>', false)
            ->assertDontSee('<td>Maaş</td>', false);

        $this->actingAs($this->admin)
            ->get(route('admin.cash-categories.index', ['direction' => 'income']))
            ->assertSee('<td>Faiz geliri</td>', false)
            ->assertDontSee('<td>Maaş</td>', false);
    }

    public function test_options_offer_matching_and_shared_categories(): void
    {
        $expense = CashCategory::factory()->create(['name' => 'Lastik']);
        $income = CashCategory::factory()->create(['name' => 'Diğer gelir', 'direction' => CashDirection::Income]);
        $both = CashCategory::factory()->create(['name' => 'Teminat', 'direction' => CashDirection::Both]);
        $passive = CashCategory::factory()->create(['name' => 'Eski', 'active' => false]);

        $repository = app(CashCategoryRepository::class);

        $this->assertSame([$expense->id, $both->id], array_keys($repository->options(CashDirection::Expense)));
        $this->assertSame([$income->id, $both->id], array_keys($repository->options(CashDirection::Income)));
        $this->assertArrayHasKey($passive->id, $repository->options(CashDirection::Expense, $passive->id));
    }
}
