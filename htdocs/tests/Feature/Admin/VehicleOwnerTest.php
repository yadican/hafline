<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleOwner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehicleOwnerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
    }

    public function test_guest_is_redirected_from_the_owner_list(): void
    {
        $this->get(route('admin.vehicle-owners.index'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_admin_can_create_update_and_delete_an_owner(): void
    {
        $this->actingAs($this->admin)->get(route('admin.vehicle-owners.create'))->assertOk();

        $this->actingAs($this->admin)
            ->post(route('admin.vehicle-owners.store'), [
                'first_name' => 'Hasan',
                'last_name' => 'Demir',
                'phone' => '0216 444 00 00',
                'mobile' => '',
                'address' => 'Tuzla',
            ])
            ->assertRedirect(route('admin.vehicle-owners.index'))
            ->assertSessionHas('success');

        $owner = VehicleOwner::query()->where('last_name', 'Demir')->firstOrFail();
        $this->assertSame('Hasan Demir', $owner->full_name);
        $this->assertNull($owner->mobile);

        $this->actingAs($this->admin)->get(route('admin.vehicle-owners.edit', $owner))->assertOk();

        $this->actingAs($this->admin)
            ->put(route('admin.vehicle-owners.update', $owner), [
                'first_name' => 'Hasan',
                'last_name' => 'Demirci',
                'phone' => '0216 444 00 00',
                'mobile' => '0532 000 00 00',
                'address' => '',
            ])
            ->assertRedirect(route('admin.vehicle-owners.index'));

        $owner->refresh();
        $this->assertSame('Demirci', $owner->last_name);
        $this->assertSame('0532 000 00 00', $owner->mobile);
        $this->assertNull($owner->address);

        $this->actingAs($this->admin)
            ->delete(route('admin.vehicle-owners.destroy', $owner))
            ->assertRedirect(route('admin.vehicle-owners.index'));

        $this->assertSoftDeleted($owner);
    }

    public function test_first_and_last_name_are_required(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.vehicle-owners.store'), ['first_name' => '', 'last_name' => ''])
            ->assertSessionHasErrors(['first_name', 'last_name']);

        $this->assertDatabaseCount('vehicle_owners', 0);
    }

    public function test_owner_with_vehicles_cannot_be_deleted(): void
    {
        $vehicle = Vehicle::factory()->create();

        $this->actingAs($this->admin)
            ->delete(route('admin.vehicle-owners.destroy', $vehicle->owner_id))
            ->assertSessionHas('error');

        $this->assertNotSoftDeleted('vehicle_owners', ['id' => $vehicle->owner_id]);
    }

    public function test_search_matches_every_word_across_name_columns(): void
    {
        VehicleOwner::factory()->create(['first_name' => 'Ali', 'last_name' => 'Yılmaz']);
        VehicleOwner::factory()->create(['first_name' => 'Ali', 'last_name' => 'Kaya']);
        VehicleOwner::factory()->create(['first_name' => 'Veli', 'last_name' => 'Yılmaz']);

        $this->actingAs($this->admin)
            ->get(route('admin.vehicle-owners.index', ['q' => 'ali yılmaz']))
            ->assertOk()
            ->assertSee('Ali Yılmaz')
            ->assertDontSee('Ali Kaya')
            ->assertDontSee('Veli Yılmaz');

        $this->actingAs($this->admin)
            ->get(route('admin.vehicle-owners.index', ['sort' => 'name', 'dir' => 'desc']))
            ->assertSeeInOrder(['Veli Yılmaz', 'Ali Yılmaz', 'Ali Kaya']);
    }
}
