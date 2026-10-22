<?php

namespace Tests\Feature\Admin;

use App\Enums\PartyRole;
use App\Models\Haul;
use App\Models\LedgerEntry;
use App\Models\Party;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleClass;
use App\Services\Ledger\HaulLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehicleTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->yonetici()->create();
    }

    public function test_guest_is_redirected_from_the_vehicle_list(): void
    {
        $this->get(route('admin.vehicles.index'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_admin_can_create_update_and_delete_a_vehicle(): void
    {
        $owner = Party::factory()->create();
        $class = VehicleClass::factory()->create();

        $this->actingAs($this->admin)->get(route('admin.vehicles.create'))->assertOk();

        $this->actingAs($this->admin)
            ->post(route('admin.vehicles.store'), [
                'plate' => '34 abc 123',
                'owner_party_id' => $owner->id,
                'owned_from' => '2026-01-01',
                'vehicle_class_id' => $class->id,
                'driver_name' => 'Mustafa Çelik',
                'active' => '1',
            ])
            ->assertRedirect(route('admin.vehicles.index'))
            ->assertSessionHas('success');

        $vehicle = Vehicle::findByPlate('34ABC123');
        $this->assertNotNull($vehicle);
        $this->assertSame('34 ABC 123', $vehicle->plate);
        $this->assertSame($owner->id, $vehicle->currentOwnership->party_id);
        $this->assertSame('2026-01-01', $vehicle->currentOwnership->started_on->toDateString());
        $this->assertTrue($owner->fresh()->hasRole(PartyRole::VehicleOwner));

        $this->actingAs($this->admin)->get(route('admin.vehicles.edit', $vehicle))->assertOk()->assertSee($owner->name);

        $this->actingAs($this->admin)
            ->put(route('admin.vehicles.update', $vehicle), [
                'plate' => '34 ABC 123',
                'owner_party_id' => Party::factory()->create()->id,
                'vehicle_class_id' => $class->id,
                'driver_name' => '',
            ])
            ->assertRedirect(route('admin.vehicles.index'));

        $vehicle->refresh();
        $this->assertSame($owner->id, $vehicle->currentOwnership->party_id, 'Owner changes only through a transfer.');
        $this->assertNull($vehicle->driver_name);
        $this->assertFalse($vehicle->active);

        $this->actingAs($this->admin)
            ->delete(route('admin.vehicles.destroy', $vehicle))
            ->assertRedirect(route('admin.vehicles.index'));

        $this->assertSoftDeleted($vehicle);
    }

    public function test_owner_is_required_when_creating(): void
    {
        $class = VehicleClass::factory()->create();

        $this->actingAs($this->admin)
            ->post(route('admin.vehicles.store'), ['plate' => '06 A 1', 'vehicle_class_id' => $class->id])
            ->assertSessionHasErrors(['owner_party_id', 'owned_from']);

        $this->assertDatabaseCount('vehicles', 0);
    }

    public function test_plate_is_unique_regardless_of_spacing_and_case(): void
    {
        $existing = Vehicle::factory()->create(['plate' => '34 TR 1907']);
        $class = VehicleClass::factory()->create();
        $owner = ['owner_party_id' => Party::factory()->create()->id, 'owned_from' => '2026-01-01'];

        $this->actingAs($this->admin)
            ->post(route('admin.vehicles.store'), ['plate' => '34tr1907', 'vehicle_class_id' => $class->id, ...$owner])
            ->assertSessionHasErrors('plate');

        $this->actingAs($this->admin)
            ->put(route('admin.vehicles.update', $existing), ['plate' => '34 TR 1907', 'vehicle_class_id' => $class->id])
            ->assertSessionHasNoErrors();

        $existing->delete();

        $this->actingAs($this->admin)
            ->post(route('admin.vehicles.store'), ['plate' => '34 TR 1907', 'vehicle_class_id' => $class->id, ...$owner])
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

    public function test_list_filters_by_plate_current_owner_and_class(): void
    {
        $owner = Party::factory()->create(['name' => 'Kemal Aslan']);
        Vehicle::factory()->ownedBy($owner)->create(['plate' => '34 KM 100', 'driver_name' => 'Ercan']);
        $other = Vehicle::factory()->create(['plate' => '41 ZZ 200', 'driver_name' => 'Orhan']);

        $this->actingAs($this->admin)
            ->get(route('admin.vehicles.index', ['q' => 'km 10']))
            ->assertOk()
            ->assertSee('34 KM 100')
            ->assertSee('Kemal Aslan')
            ->assertDontSee('41 ZZ 200');

        $this->actingAs($this->admin)
            ->get(route('admin.vehicles.index', ['q' => 'Orhan']))
            ->assertSee('41 ZZ 200')
            ->assertDontSee('34 KM 100');

        $this->actingAs($this->admin)
            ->get(route('admin.vehicles.index', ['owner' => $owner->id, 'sort' => 'owner']))
            ->assertSee('34 KM 100')
            ->assertDontSee('41 ZZ 200');

        $this->actingAs($this->admin)
            ->get(route('admin.vehicles.index', ['class' => $other->vehicle_class_id, 'sort' => 'driver', 'dir' => 'desc']))
            ->assertSee('41 ZZ 200')
            ->assertDontSee('34 KM 100');
    }

    public function test_transfer_moves_later_hauls_and_their_ledger_to_the_new_owner(): void
    {
        $seller = Party::factory()->create();
        $buyer = Party::factory()->create();
        $vehicle = Vehicle::factory()->ownedBy($seller, '2026-01-01')->create();
        $before = $this->haul($vehicle, '2026-03-10 10:00', 1000);
        $after = $this->haul($vehicle, '2026-03-20 10:00', 2000);

        $this->actingAs($this->admin)
            ->post(route('admin.vehicles.ownerships.store', $vehicle), ['party_id' => $buyer->id, 'started_on' => '2026-03-15'])
            ->assertRedirect(route('admin.vehicles.edit', $vehicle))
            ->assertSessionHas('success');

        $ownerships = $vehicle->ownerships()->get();
        $this->assertCount(2, $ownerships);
        $this->assertSame('2026-03-14', $ownerships[1]->ended_on->toDateString());
        $this->assertSame($buyer->id, $vehicle->fresh()->currentOwnership->party_id);
        $this->assertTrue($buyer->fresh()->hasRole(PartyRole::VehicleOwner));

        $this->assertSame($seller->id, $before->fresh()->party_id);
        $this->assertSame($buyer->id, $after->fresh()->party_id);
        $this->assertSame(1000.0, $this->balance($seller) * -1);
        $this->assertSame(2000.0, $this->balance($buyer) * -1);
        $this->assertSame(1, LedgerEntry::query()->whereNotNull('reversal_of')->where('party_id', $seller->id)->count());
    }

    public function test_transfer_must_start_after_the_current_period_and_change_the_owner(): void
    {
        $owner = Party::factory()->create();
        $vehicle = Vehicle::factory()->ownedBy($owner, '2026-02-01')->create();

        $this->actingAs($this->admin)
            ->post(route('admin.vehicles.ownerships.store', $vehicle), ['party_id' => Party::factory()->create()->id, 'started_on' => '2026-02-01'])
            ->assertSessionHas('error');

        $this->actingAs($this->admin)
            ->post(route('admin.vehicles.ownerships.store', $vehicle), ['party_id' => $owner->id, 'started_on' => '2026-03-01'])
            ->assertSessionHas('error', 'Araç zaten bu cariye kayıtlı.');

        $this->assertSame(1, $vehicle->ownerships()->count());
    }

    public function test_undoing_the_last_transfer_gives_hauls_back_to_the_previous_owner(): void
    {
        $seller = Party::factory()->create();
        $buyer = Party::factory()->create();
        $vehicle = Vehicle::factory()->ownedBy($seller, '2026-01-01')->create();
        $haul = $this->haul($vehicle, '2026-03-20 10:00', 1500);

        $this->actingAs($this->admin)
            ->post(route('admin.vehicles.ownerships.store', $vehicle), ['party_id' => $buyer->id, 'started_on' => '2026-03-15']);
        $this->assertSame($buyer->id, $haul->fresh()->party_id);

        $first = $vehicle->ownerships()->get()->last();

        $this->actingAs($this->admin)
            ->delete(route('admin.vehicles.ownerships.destroy', [$vehicle, $first]))
            ->assertSessionHas('error');

        $latest = $vehicle->fresh()->currentOwnership;

        $this->actingAs($this->admin)
            ->delete(route('admin.vehicles.ownerships.destroy', [$vehicle, $latest]))
            ->assertRedirect(route('admin.vehicles.edit', $vehicle))
            ->assertSessionHas('success');

        $this->assertSame(1, $vehicle->ownerships()->count());
        $this->assertNull($vehicle->fresh()->currentOwnership->ended_on);
        $this->assertSame($seller->id, $haul->fresh()->party_id);
        $this->assertSame(-1500.0, $this->balance($seller));
        $this->assertSame(0.0, $this->balance($buyer));
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

    private function haul(Vehicle $vehicle, string $hauledAt, float $price): Haul
    {
        $haul = Haul::factory()->create([
            'vehicle_id' => $vehicle->id,
            'party_id' => $vehicle->ownershipOn(now()->parse($hauledAt))->party_id,
            'hauled_at' => $hauledAt,
            'price' => $price,
            'dump_price' => 0,
        ]);

        app(HaulLedger::class)->sync($haul);

        return $haul;
    }

    private function balance(Party $party): float
    {
        return (float) LedgerEntry::query()->where('party_id', $party->id)->sum('debit')
            - (float) LedgerEntry::query()->where('party_id', $party->id)->sum('credit');
    }
}
