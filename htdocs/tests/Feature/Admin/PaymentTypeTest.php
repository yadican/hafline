<?php

namespace Tests\Feature\Admin;

use App\Enums\PaymentKind;
use App\Models\PaymentType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentTypeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
    }

    public function test_guest_is_redirected_from_the_payment_type_list(): void
    {
        $this->get(route('admin.payment-types.index'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_admin_can_create_update_and_delete_a_payment_type(): void
    {
        $this->actingAs($this->admin)->get(route('admin.payment-types.create'))->assertOk();

        $this->actingAs($this->admin)
            ->post(route('admin.payment-types.store'), ['name' => 'Nakit', 'kind' => 'cash', 'active' => '1'])
            ->assertRedirect(route('admin.payment-types.index'))
            ->assertSessionHas('success');

        $paymentType = PaymentType::query()->where('name', 'Nakit')->firstOrFail();
        $this->assertSame(PaymentKind::Cash, $paymentType->kind);
        $this->assertTrue($paymentType->active);

        $this->actingAs($this->admin)->get(route('admin.payment-types.edit', $paymentType))->assertOk();

        $this->actingAs($this->admin)
            ->put(route('admin.payment-types.update', $paymentType), ['name' => 'Nakit ödeme', 'kind' => 'cash'])
            ->assertRedirect(route('admin.payment-types.index'));

        $paymentType->refresh();
        $this->assertSame('Nakit ödeme', $paymentType->name);
        $this->assertFalse($paymentType->active);

        $this->actingAs($this->admin)
            ->delete(route('admin.payment-types.destroy', $paymentType))
            ->assertRedirect(route('admin.payment-types.index'));

        $this->assertSoftDeleted($paymentType);
    }

    public function test_name_must_be_unique_among_active_records_and_kind_must_be_known(): void
    {
        PaymentType::factory()->create(['name' => 'Çek']);
        PaymentType::factory()->create(['name' => 'Eski tip'])->delete();

        $this->actingAs($this->admin)
            ->from(route('admin.payment-types.create'))
            ->post(route('admin.payment-types.store'), ['name' => 'Çek', 'kind' => 'gold'])
            ->assertRedirect(route('admin.payment-types.create'))
            ->assertSessionHasErrors(['name', 'kind']);

        $this->actingAs($this->admin)
            ->post(route('admin.payment-types.store'), ['name' => 'Eski tip', 'kind' => 'other'])
            ->assertSessionHasNoErrors();

        $existing = PaymentType::query()->where('name', 'Çek')->firstOrFail();

        $this->actingAs($this->admin)
            ->put(route('admin.payment-types.update', $existing), ['name' => 'Çek', 'kind' => 'cheque', 'active' => '1'])
            ->assertSessionHasNoErrors();
    }

    public function test_list_filters_by_name_kind_and_status_and_sorts_by_name(): void
    {
        PaymentType::factory()->create(['name' => 'Peşin', 'kind' => 'cash']);
        PaymentType::factory()->create(['name' => 'Havale', 'kind' => 'bank']);
        PaymentType::factory()->create(['name' => 'Arşiv', 'kind' => 'other', 'active' => false]);

        $this->actingAs($this->admin)
            ->get(route('admin.payment-types.index'))
            ->assertOk()
            ->assertSeeInOrder(['Arşiv', 'Havale', 'Peşin']);

        $this->actingAs($this->admin)
            ->get(route('admin.payment-types.index', ['kind' => 'bank']))
            ->assertSee('Havale')
            ->assertDontSee('Peşin');

        $this->actingAs($this->admin)
            ->get(route('admin.payment-types.index', ['active' => '0']))
            ->assertSee('Arşiv')
            ->assertDontSee('Havale');

        $this->actingAs($this->admin)
            ->get(route('admin.payment-types.index', ['sort' => 'name', 'dir' => 'desc']))
            ->assertSeeInOrder(['Peşin', 'Havale', 'Arşiv']);

        $this->actingAs($this->admin)
            ->get(route('admin.payment-types.index', ['q' => 'yok']))
            ->assertSee('Aramanızla eşleşen kayıt bulunamadı.');
    }
}
