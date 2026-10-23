<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehicleClassTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->yonetici()->create();
    }

    public function test_guest_is_redirected_from_the_vehicle_class_list(): void
    {
        $this->get(route('admin.vehicle-classes.index'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_admin_can_create_update_and_delete_a_vehicle_class(): void
    {
        $this->actingAs($this->admin)->get(route('admin.vehicle-classes.create'))->assertOk();

        $this->actingAs($this->admin)
            ->post(route('admin.vehicle-classes.store'), ['name' => 'TIR', 'active' => '1'])
            ->assertRedirect(route('admin.vehicle-classes.index'))
            ->assertSessionHas('success');

        $class = VehicleClass::query()->where('name', 'TIR')->firstOrFail();

        $this->actingAs($this->admin)->get(route('admin.vehicle-classes.show', $class))->assertOk();
        $this->actingAs($this->admin)->get(route('admin.vehicle-classes.edit', $class))->assertOk();

        $this->actingAs($this->admin)
            ->put(route('admin.vehicle-classes.update', $class), ['name' => 'TIR (8x4)', 'active' => '1'])
            ->assertRedirect(route('admin.vehicle-classes.index'));

        $this->assertSame('TIR (8x4)', $class->refresh()->name);

        $this->actingAs($this->admin)
            ->delete(route('admin.vehicle-classes.destroy', $class))
            ->assertRedirect(route('admin.vehicle-classes.index'));

        $this->assertSoftDeleted($class);
    }

    public function test_class_names_are_unique_and_short(): void
    {
        VehicleClass::factory()->create(['name' => 'KAMYON']);

        $this->actingAs($this->admin)
            ->post(route('admin.vehicle-classes.store'), ['name' => 'KAMYON'])
            ->assertSessionHasErrors('name');

        $this->actingAs($this->admin)
            ->post(route('admin.vehicle-classes.store'), ['name' => str_repeat('A', 61)])
            ->assertSessionHasErrors('name');
    }

    public function test_class_with_vehicles_cannot_be_deleted(): void
    {
        $vehicle = Vehicle::factory()->create();

        $this->actingAs($this->admin)
            ->delete(route('admin.vehicle-classes.destroy', $vehicle->vehicle_class_id))
            ->assertSessionHas('error', fn (string $message) => str_contains($message, 'araç'));

        $this->assertNotSoftDeleted('vehicle_classes', ['id' => $vehicle->vehicle_class_id]);
    }

    public function test_list_links_vehicle_counts_to_the_filtered_vehicle_list(): void
    {
        $class = VehicleClass::factory()->create(['name' => 'DAMPERLİ']);
        Vehicle::factory()->count(3)->create(['vehicle_class_id' => $class->id]);

        $this->actingAs($this->admin)
            ->get(route('admin.vehicle-classes.index'))
            ->assertOk()
            ->assertSee(route('admin.vehicles.index', ['class' => $class->id]), false);
    }
}
