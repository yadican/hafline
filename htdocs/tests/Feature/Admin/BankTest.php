<?php

namespace Tests\Feature\Admin;

use App\Models\Bank;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BankTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
    }

    public function test_guest_is_redirected_from_the_bank_list(): void
    {
        $this->get(route('admin.banks.index'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_admin_can_create_update_and_delete_a_bank(): void
    {
        $this->actingAs($this->admin)->get(route('admin.banks.create'))->assertOk();

        $this->actingAs($this->admin)
            ->post(route('admin.banks.store'), [
                'name' => 'Ziraat Bankası',
                'representative' => 'Ayşe Yılmaz',
                'phone' => '0212 555 00 00',
                'account_no' => '',
                'active' => '1',
            ])
            ->assertRedirect(route('admin.banks.index'))
            ->assertSessionHas('success');

        $bank = Bank::query()->where('name', 'Ziraat Bankası')->firstOrFail();
        $this->assertSame('Ayşe Yılmaz', $bank->representative);
        $this->assertNull($bank->account_no);

        $this->actingAs($this->admin)->get(route('admin.banks.edit', $bank))->assertOk();

        $this->actingAs($this->admin)
            ->put(route('admin.banks.update', $bank), [
                'name' => 'Ziraat Bankası',
                'representative' => '',
                'account_no' => 'TR12 0001 0000 0000 0000 0000 01',
                'active' => '1',
            ])
            ->assertRedirect(route('admin.banks.index'));

        $bank->refresh();
        $this->assertNull($bank->representative);
        $this->assertSame('TR12 0001 0000 0000 0000 0000 01', $bank->account_no);

        $this->actingAs($this->admin)
            ->delete(route('admin.banks.destroy', $bank))
            ->assertRedirect(route('admin.banks.index'));

        $this->assertSoftDeleted($bank);
    }

    public function test_invalid_bank_is_not_stored(): void
    {
        $this->actingAs($this->admin)
            ->from(route('admin.banks.create'))
            ->post(route('admin.banks.store'), ['name' => '', 'phone' => str_repeat('1', 21)])
            ->assertRedirect(route('admin.banks.create'))
            ->assertSessionHasErrors(['name', 'phone']);

        $this->assertDatabaseCount('banks', 0);
    }

    public function test_list_searches_across_contact_fields_and_sorts(): void
    {
        Bank::factory()->create(['name' => 'Akbank', 'representative' => 'Mehmet Kaya']);
        Bank::factory()->create(['name' => 'Vakıfbank', 'representative' => 'Zeynep Ak']);

        $this->actingAs($this->admin)
            ->get(route('admin.banks.index', ['q' => 'zeynep']))
            ->assertOk()
            ->assertSee('Vakıfbank')
            ->assertDontSee('Akbank');

        $this->actingAs($this->admin)
            ->get(route('admin.banks.index', ['sort' => 'representative', 'dir' => 'desc']))
            ->assertSeeInOrder(['Zeynep Ak', 'Mehmet Kaya']);

        $this->actingAs($this->admin)
            ->get(route('admin.banks.index', ['sort' => 'phone']))
            ->assertRedirect();
    }
}
