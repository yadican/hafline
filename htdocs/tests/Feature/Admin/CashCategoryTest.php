<?php

namespace Tests\Feature\Admin;

use App\Models\CashCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashCategoryTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
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
            ->post(route('admin.cash-categories.store'), ['name' => 'Yakıt', 'active' => '1'])
            ->assertRedirect(route('admin.cash-categories.index'))
            ->assertSessionHas('success');

        $category = CashCategory::query()->where('name', 'Yakıt')->firstOrFail();

        $this->actingAs($this->admin)->get(route('admin.cash-categories.edit', $category))->assertOk();

        $this->actingAs($this->admin)
            ->put(route('admin.cash-categories.update', $category), ['name' => 'Yakıt giderleri'])
            ->assertRedirect(route('admin.cash-categories.index'));

        $category->refresh();
        $this->assertSame('Yakıt giderleri', $category->name);
        $this->assertFalse($category->active);

        $this->actingAs($this->admin)
            ->delete(route('admin.cash-categories.destroy', $category))
            ->assertRedirect(route('admin.cash-categories.index'));

        $this->assertSoftDeleted($category);
    }

    public function test_name_is_required(): void
    {
        $this->actingAs($this->admin)
            ->from(route('admin.cash-categories.create'))
            ->post(route('admin.cash-categories.store'), ['name' => ''])
            ->assertRedirect(route('admin.cash-categories.create'))
            ->assertSessionHasErrors('name');

        $this->assertDatabaseCount('cash_categories', 0);
    }

    public function test_list_searches_and_filters_by_status(): void
    {
        CashCategory::factory()->create(['name' => 'Maaş']);
        CashCategory::factory()->create(['name' => 'Kira', 'active' => false]);

        $this->actingAs($this->admin)
            ->get(route('admin.cash-categories.index', ['q' => 'maa']))
            ->assertOk()
            ->assertSee('Maaş')
            ->assertDontSee('Kira');

        $this->actingAs($this->admin)
            ->get(route('admin.cash-categories.index', ['active' => '0']))
            ->assertSee('Kira')
            ->assertDontSee('Maaş');
    }
}
