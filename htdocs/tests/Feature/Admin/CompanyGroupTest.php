<?php

namespace Tests\Feature\Admin;

use App\Models\CompanyGroup;
use App\Models\Party;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyGroupTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->yonetici()->create();
    }

    public function test_guest_is_redirected(): void
    {
        $this->get(route('admin.company-groups.index'))->assertRedirect(route('admin.login'));
    }

    public function test_admin_can_create_update_and_delete_a_group(): void
    {
        $this->actingAs($this->admin)->get(route('admin.company-groups.create'))->assertOk();

        $this->actingAs($this->admin)
            ->post(route('admin.company-groups.store'), ['name' => 'YILDIZ GRUBU', 'active' => '1'])
            ->assertRedirect(route('admin.company-groups.index'))
            ->assertSessionHas('success');

        $group = CompanyGroup::query()->sole();
        $this->assertTrue($group->active);

        $this->actingAs($this->admin)
            ->post(route('admin.company-groups.store'), ['name' => 'YILDIZ GRUBU'])
            ->assertSessionHasErrors('name');

        $this->actingAs($this->admin)->get(route('admin.company-groups.show', $group))->assertOk()->assertSee('YILDIZ GRUBU');

        $this->actingAs($this->admin)
            ->put(route('admin.company-groups.update', $group), ['name' => 'YILDIZ HOLDİNG'])
            ->assertRedirect(route('admin.company-groups.index'));

        $this->assertSame('YILDIZ HOLDİNG', $group->fresh()->name);
        $this->assertFalse($group->fresh()->active);

        $this->actingAs($this->admin)
            ->delete(route('admin.company-groups.destroy', $group))
            ->assertRedirect(route('admin.company-groups.index'));

        $this->assertSoftDeleted($group);
    }

    public function test_company_joins_a_group_from_its_card_and_a_group_with_companies_cannot_be_deleted(): void
    {
        $group = CompanyGroup::factory()->create(['name' => 'YILDIZ GRUBU']);
        $company = Party::factory()->company()->create(['name' => 'YILDIZ İNŞAAT A.Ş.']);
        Party::factory()->company()->create(['name' => 'BAŞKA A.Ş.']);

        $this->actingAs($this->admin)
            ->get(route('admin.parties.edit', $company))
            ->assertOk()
            ->assertSee('YILDIZ GRUBU');

        $this->actingAs($this->admin)
            ->put(route('admin.parties.update', $company), ['title' => 'YILDIZ İNŞAAT A.Ş.', 'active' => '1', 'company_group_id' => $group->id])
            ->assertSessionHasNoErrors();

        $this->assertSame($group->id, $company->company->fresh()->company_group_id);

        $this->actingAs($this->admin)
            ->get(route('admin.companies.index', ['group' => $group->id]))
            ->assertSee('YILDIZ İNŞAAT A.Ş.')
            ->assertDontSee('BAŞKA A.Ş.');

        $this->actingAs($this->admin)
            ->get(route('admin.company-groups.show', $group))
            ->assertSee('YILDIZ İNŞAAT A.Ş.');

        $this->actingAs($this->admin)
            ->delete(route('admin.company-groups.destroy', $group))
            ->assertSessionHas('error');

        $this->assertNotSoftDeleted($group);
    }
}
