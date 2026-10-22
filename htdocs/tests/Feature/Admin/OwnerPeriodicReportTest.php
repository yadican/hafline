<?php

namespace Tests\Feature\Admin;

use App\Enums\PartyRole;
use App\Models\DumpSite;
use App\Models\FuelReceipt;
use App\Models\FuelStation;
use App\Models\Haul;
use App\Models\HaulRoute;
use App\Models\Party;
use App\Models\Site;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OwnerPeriodicReportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Party $owner;

    private Vehicle $vehicleA;

    private Vehicle $vehicleB;

    private HaulRoute $route;

    private FuelStation $station;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
        $this->owner = Party::factory()->person()->withRole(PartyRole::VehicleOwner)->create([
            'name' => 'AHMET YILMAZ',
        ]);
        $this->vehicleA = Vehicle::factory()->ownedBy($this->owner)->create(['plate' => '34 AAA 01']);
        $this->vehicleB = Vehicle::factory()->ownedBy($this->owner)->create(['plate' => '34 BBB 01']);
        $site = Site::factory()->create(['name' => 'TOPKAPI']);
        $dump = DumpSite::factory()->create(['name' => '212']);
        $this->route = HaulRoute::factory()->create([
            'site_id' => $site->id,
            'dump_site_id' => $dump->id,
        ]);
        $this->station = FuelStation::factory()->create(['name' => 'SHELL']);
    }

    public function test_guest_is_redirected(): void
    {
        $this->get(route('admin.reports.owner-periodic'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_report_groups_by_vehicle_using_owner_snapshot(): void
    {
        $otherOwner = Party::factory()->person()->withRole(PartyRole::VehicleOwner)->create();

        Haul::factory()->create([
            'vehicle_id' => $this->vehicleA->id,
            'party_id' => $this->owner->id,
            'route_id' => $this->route->id,
            'site_id' => $this->route->site_id,
            'dump_site_id' => $this->route->dump_site_id,
            'dump_party_id' => $this->route->dumpSite->party_id,
            'tonnage' => 10,
            'hauled_at' => '2026-09-05 10:00:00',
        ]);
        Haul::factory()->create([
            'vehicle_id' => $this->vehicleB->id,
            'party_id' => $this->owner->id,
            'route_id' => $this->route->id,
            'site_id' => $this->route->site_id,
            'dump_site_id' => $this->route->dump_site_id,
            'dump_party_id' => $this->route->dumpSite->party_id,
            'tonnage' => 5,
            'hauled_at' => '2026-09-06 10:00:00',
        ]);
        // Wrong owner snapshot — must not appear.
        Haul::factory()->create([
            'vehicle_id' => $this->vehicleA->id,
            'party_id' => $otherOwner->id,
            'route_id' => $this->route->id,
            'site_id' => $this->route->site_id,
            'dump_site_id' => $this->route->dump_site_id,
            'dump_party_id' => $this->route->dumpSite->party_id,
            'tonnage' => 99,
            'hauled_at' => '2026-09-07 10:00:00',
        ]);
        FuelReceipt::factory()->create([
            'vehicle_id' => $this->vehicleA->id,
            'party_id' => $this->owner->id,
            'fuel_station_id' => $this->station->id,
            'fuel_party_id' => $this->station->party_id,
            'quantity' => 40,
            'issued_on' => '2026-09-08',
        ]);

        $report = $this->actingAs($this->admin)
            ->get(route('admin.reports.owner-periodic', [
                'owner' => $this->owner->id,
                'period' => 'month',
                'year' => 2026,
                'month' => 9,
            ]))
            ->assertOk()
            ->assertSee('AHMET YILMAZ', false)
            ->viewData('report');

        $this->assertCount(2, $report['vehicles']);
        $byPlate = collect($report['vehicles'])->keyBy('plate');
        $this->assertSame(1, $byPlate['34 AAA 01']['total_trips']);
        $this->assertSame(40.0, $byPlate['34 AAA 01']['total_liters']);
        $this->assertSame(1, $byPlate['34 BBB 01']['total_trips']);
        $this->assertSame(2, $report['total_trips']);
        $this->assertSame(15.0, $report['total_tonnage']);
        $this->assertSame(40.0, $report['total_liters']);
    }
}
