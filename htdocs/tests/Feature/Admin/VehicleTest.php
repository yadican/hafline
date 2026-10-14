<?php

namespace Tests\Feature\Admin;

use App\Models\Haul;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleClass;
use App\Models\VehicleOwner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehicleTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
    }

    public function test_guest_is_redirected_from_the_vehicle_list(): void
    {
        $this->get(route('admin.vehicles.index'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_admin_can_create_update_and_delete_a_vehicle(): void
    {
        $owner = VehicleOwner::factory()->create();
        $class = VehicleClass::factory()->create();

        $this->actingAs($this->admin)->get(route('admin.vehicles.create'))->assertOk();

        $this->actingAs($this->admin)
            ->post(route('admin.vehicles.store'), [
                'plate' => '34 abc 123',
                'owner_id' => $owner->id,
                'vehicle_class_id' => $class->id,
                'driver_name' => 'Mustafa Çelik',
                'active' => '1',
            ])
            ->assertRedirect(route('admin.vehicles.index'))
            ->assertSessionHas('success');

        $vehicle = Vehicle::findByPlate('34ABC123');
        $this->assertNotNull($vehicle);
        $this->assertSame('34 ABC 123', $vehicle->plate);
        $this->assertSame($owner->id, $vehicle->owner_id);

        $this->actingAs($this->admin)->get(route('admin.vehicles.edit', $vehicle))->assertOk();

        $this->actingAs($this->admin)
            ->put(route('admin.vehicles.update', $vehicle), [
                'plate' => '34 ABC 123',
                'owner_id' => '',
                'vehicle_class_id' => $class->id,
                'driver_name' => '',
            ])
            ->assertRedirect(route('admin.vehicles.index'));

        $vehicle->refresh();
        $this->assertNull($vehicle->owner_id);
        $this->assertNull($vehicle->driver_name);
        $this->assertFalse($vehicle->active);

        $this->actingAs($this->admin)
            ->delete(route('admin.vehicles.destroy', $vehicle))
            ->assertRedirect(route('admin.vehicles.index'));

        $this->assertSoftDeleted($vehicle);
    }

    public function test_plate_is_unique_regardless_of_spacing_and_case(): void
    {
        $existing = Vehicle::factory()->create(['plate' => '34 TR 1907']);
        $class = VehicleClass::factory()->create();

        $this->actingAs($this->admin)
            ->post(route('admin.vehicles.store'), ['plate' => '34tr1907', 'vehicle_class_id' => $class->id])
            ->assertSessionHasErrors('plate');

        $this->actingAs($this->admin)
            ->put(route('admin.vehicles.update', $existing), ['plate' => '34 TR 1907', 'vehicle_class_id' => $class->id])
            ->assertSessionHasNoErrors();

        $existing->delete();

        $this->actingAs($this->admin)
            ->post(route('admin.vehicles.store'), ['plate' => '34 TR 1907', 'vehicle_class_id' => $class->id])
            ->assertSessionHasNoErrors();
    }

    public function test_class_is_required_and_must_exist(): void
    {
        $deleted = VehicleClass::factory()->create();
        $deleted->delete();

        $this->actingAs($this->admin)
            ->post(route('admin.vehicles.store'), ['plate' => '06 A 1'])
            ->assertSessionHasErrors('vehicle_class_id');

        $this->actingAs($this->admin)
            ->post(route('admin.vehicles.store'), ['plate' => '06 A 1', 'vehicle_class_id' => $deleted->id])
            ->assertSessionHasErrors('vehicle_class_id');

        $this->assertDatabaseCount('vehicles', 0);
    }

    public function test_list_filters_by_plate_owner_and_class(): void
    {
        $owner = VehicleOwner::factory()->create(['first_name' => 'Kemal', 'last_name' => 'Aslan']);
        Vehicle::factory()->create(['plate' => '34 KM 100', 'owner_id' => $owner->id, 'driver_name' => 'Ercan']);
        $other = Vehicle::factory()->create(['plate' => '41 ZZ 200', 'driver_name' => 'Orhan']);

        $this->actingAs($this->admin)
            ->get(route('admin.vehicles.index', ['q' => 'km 10']))
            ->assertOk()
            ->assertSee('34 KM 100')
            ->assertDontSee('41 ZZ 200');

        $this->actingAs($this->admin)
            ->get(route('admin.vehicles.index', ['q' => 'Orhan']))
            ->assertSee('41 ZZ 200')
            ->assertDontSee('34 KM 100');

        $this->actingAs($this->admin)
            ->get(route('admin.vehicles.index', ['owner' => $owner->id]))
            ->assertSee('34 KM 100')
            ->assertDontSee('41 ZZ 200');

        $this->actingAs($this->admin)
            ->get(route('admin.vehicles.index', ['class' => $other->vehicle_class_id, 'sort' => 'driver', 'dir' => 'desc']))
            ->assertSee('41 ZZ 200')
            ->assertDontSee('34 KM 100');
    }

    public function test_inactive_vehicles_are_left_out_of_the_plate_lookup(): void
    {
        Vehicle::factory()->create(['plate' => '34 AK 1']);
        Vehicle::factory()->create(['plate' => '34 AK 2', 'active' => false]);

        $this->actingAs($this->admin)
            ->getJson(route('admin.hauls.lookups.vehicles', ['q' => '34AK']))
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.value', '34 AK 1');
    }

    public function test_deleted_vehicle_still_shows_on_its_past_hauls(): void
    {
        $haul = Haul::factory()->create();
        $plate = $haul->vehicle->plate;
        $haul->vehicle->delete();

        $this->assertSame($plate, $haul->fresh()->vehicle?->plate);
    }
}
