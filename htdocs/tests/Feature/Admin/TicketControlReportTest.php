<?php

namespace Tests\Feature\Admin;

use App\Models\DumpSite;
use App\Models\FuelReceipt;
use App\Models\FuelStation;
use App\Models\Haul;
use App\Models\HaulRoute;
use App\Models\Site;
use App\Models\TicketHandover;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketControlReportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Site $site;

    private DumpSite $dumpSite;

    private HaulRoute $route;

    private Vehicle $vehicle;

    private FuelStation $station;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->yonetici()->create();
        $this->site = Site::factory()->create(['name' => 'TOPKAPI']);
        $this->dumpSite = DumpSite::factory()->create(['name' => '212']);
        $this->route = HaulRoute::factory()->create([
            'site_id' => $this->site->id,
            'dump_site_id' => $this->dumpSite->id,
        ]);
        $this->vehicle = Vehicle::factory()->create(['plate' => '34 AAA 01']);
        $this->station = FuelStation::factory()->create(['name' => 'SHELL']);
    }

    public function test_guest_is_redirected(): void
    {
        $this->get(route('admin.reports.ticket-control'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_totals_without_grouping(): void
    {
        TicketHandover::factory()->create([
            'vehicle_id' => $this->vehicle->id,
            'route_id' => $this->route->id,
            'haul_count' => 5,
            'handed_on' => '2026-09-05',
        ]);
        TicketHandover::factory()->create([
            'vehicle_id' => $this->vehicle->id,
            'route_id' => $this->route->id,
            'haul_count' => 3,
            'handed_on' => '2026-09-10',
        ]);

        Haul::factory()->count(4)->create([
            'vehicle_id' => $this->vehicle->id,
            'party_id' => $this->vehicle->currentOwnership->party_id,
            'route_id' => $this->route->id,
            'site_id' => $this->site->id,
            'dump_site_id' => $this->dumpSite->id,
            'dump_party_id' => $this->dumpSite->party_id,
            'hauled_at' => '2026-09-08 10:00:00',
        ]);

        FuelReceipt::factory()->create([
            'vehicle_id' => $this->vehicle->id,
            'party_id' => $this->vehicle->currentOwnership->party_id,
            'fuel_station_id' => $this->station->id,
            'fuel_party_id' => $this->station->party_id,
            'site_id' => $this->site->id,
            'haul_ticket_count' => 7,
            'issued_on' => '2026-09-09',
        ]);

        $report = $this->actingAs($this->admin)
            ->get(route('admin.reports.ticket-control', [
                'period' => 'month',
                'year' => 2026,
                'month' => 9,
                'group' => 'none',
            ]))
            ->assertOk()
            ->assertSee('Fiş Teslim', false)
            ->viewData('report');

        $this->assertSame(8, $report['handover_total']);
        $this->assertSame(4, $report['haul_total']);
        $this->assertSame(7, $report['fuel_total']);
        $this->assertFalse($report['fuel_na']);
    }

    public function test_dump_site_filter_marks_fuel_na_and_site_group_works(): void
    {
        TicketHandover::factory()->create([
            'vehicle_id' => $this->vehicle->id,
            'route_id' => $this->route->id,
            'haul_count' => 2,
            'handed_on' => '2026-09-05',
        ]);
        Haul::factory()->create([
            'vehicle_id' => $this->vehicle->id,
            'party_id' => $this->vehicle->currentOwnership->party_id,
            'route_id' => $this->route->id,
            'site_id' => $this->site->id,
            'dump_site_id' => $this->dumpSite->id,
            'dump_party_id' => $this->dumpSite->party_id,
            'hauled_at' => '2026-09-08 10:00:00',
        ]);

        $report = $this->actingAs($this->admin)
            ->get(route('admin.reports.ticket-control', [
                'period' => 'month',
                'year' => 2026,
                'month' => 9,
                'dump_site' => $this->dumpSite->id,
                'group' => 'site',
            ]))
            ->assertOk()
            ->assertSee('uygulanamaz', false)
            ->viewData('report');

        $this->assertTrue($report['fuel_na']);
        $this->assertSame(2, $report['handover_total']);
        $this->assertSame(1, $report['haul_total']);
        $this->assertSame('TOPKAPI', $report['hauls'][0]['label']);
    }
}
