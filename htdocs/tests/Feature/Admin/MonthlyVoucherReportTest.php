<?php

namespace Tests\Feature\Admin;

use App\Models\DumpSite;
use App\Models\Haul;
use App\Models\HaulRoute;
use App\Models\Site;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonthlyVoucherReportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private HaulRoute $route;

    private VehicleClass $tir;

    private Vehicle $vehicleA;

    private Vehicle $vehicleB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->yonetici()->create();
        $site = Site::factory()->create(['name' => 'TOPKAPI']);
        $dump = DumpSite::factory()->create(['name' => '212']);
        $this->route = HaulRoute::factory()->create([
            'site_id' => $site->id,
            'dump_site_id' => $dump->id,
        ]);
        $this->tir = VehicleClass::factory()->create(['name' => 'TIR']);
        $this->vehicleA = Vehicle::factory()->create([
            'plate' => '34 AAA 01',
            'vehicle_class_id' => $this->tir->id,
        ]);
        $this->vehicleB = Vehicle::factory()->create([
            'plate' => '34 BBB 01',
            'vehicle_class_id' => $this->tir->id,
        ]);
    }

    public function test_guest_is_redirected(): void
    {
        $this->get(route('admin.reports.monthly-vouchers'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_filter_page_embeds_both_route_label_orientations(): void
    {
        $options = $this->actingAs($this->admin)
            ->get(route('admin.reports.monthly-vouchers'))
            ->assertOk()
            ->assertSee('data-monthly-voucher-routes', false)
            ->assertSee('TOPKAPI – 212', false)
            ->assertSee('212 – TOPKAPI', false)
            ->viewData('options');

        $this->assertSame('TOPKAPI – 212', $options['routesByOrientation']['site_dump'][$this->route->id]);
        $this->assertSame('212 – TOPKAPI', $options['routesByOrientation']['dump_site'][$this->route->id]);
    }

    public function test_matrix_counts_by_day_and_respects_day_range(): void
    {
        $this->makeHaul($this->vehicleA, '2026-09-01 08:00:00');
        $this->makeHaul($this->vehicleA, '2026-09-01 14:00:00');
        $this->makeHaul($this->vehicleA, '2026-09-05 10:00:00');
        $this->makeHaul($this->vehicleB, '2026-09-05 11:00:00');
        $this->makeHaul($this->vehicleA, '2026-09-20 10:00:00');

        $report = $this->actingAs($this->admin)
            ->get(route('admin.reports.monthly-vouchers', [
                'orientation' => 'site_dump',
                'route' => $this->route->id,
                'year' => 2026,
                'month' => 9,
                'day_from' => 1,
                'day_to' => 10,
            ]))
            ->assertOk()
            ->assertSee('TOPKAPI – 212', false)
            ->viewData('report');

        $this->assertSame(range(1, 10), $report['days']);
        $this->assertFalse($report['show_class']);

        $byPlate = collect($report['rows'])->keyBy('plate');
        $this->assertSame(2, $byPlate['34 AAA 01']['counts'][1]);
        $this->assertSame(1, $byPlate['34 AAA 01']['counts'][5]);
        $this->assertArrayNotHasKey(20, $byPlate['34 AAA 01']['counts']);
        $this->assertSame(3, $byPlate['34 AAA 01']['total']);
        $this->assertSame(1, $byPlate['34 BBB 01']['counts'][5]);
        $this->assertSame(4, $report['grand_total']);
    }

    public function test_dump_site_orientation_shows_class_column(): void
    {
        $this->makeHaul($this->vehicleA, '2026-09-03 10:00:00');

        $report = $this->actingAs($this->admin)
            ->get(route('admin.reports.monthly-vouchers', [
                'orientation' => 'dump_site',
                'route' => $this->route->id,
                'year' => 2026,
                'month' => 9,
            ]))
            ->assertOk()
            ->assertSee('212 – TOPKAPI', false)
            ->assertSee('Araç Sınıfı', false)
            ->viewData('report');

        $this->assertTrue($report['show_class']);
        $this->assertSame('TIR', $report['rows'][0]['class_name']);
    }

    public function test_class_filter_and_csv_export(): void
    {
        $otherClass = VehicleClass::factory()->create(['name' => 'MAKİNE']);
        $makine = Vehicle::factory()->create([
            'plate' => '34 MAK 01',
            'vehicle_class_id' => $otherClass->id,
        ]);

        $this->makeHaul($this->vehicleA, '2026-09-04 10:00:00');
        $this->makeHaul($makine, '2026-09-04 11:00:00');

        $report = $this->actingAs($this->admin)
            ->get(route('admin.reports.monthly-vouchers', [
                'orientation' => 'site_dump',
                'route' => $this->route->id,
                'year' => 2026,
                'month' => 9,
                'class' => $this->tir->id,
            ]))
            ->viewData('report');

        $this->assertCount(1, $report['rows']);
        $this->assertSame('34 AAA 01', $report['rows'][0]['plate']);

        $csv = $this->actingAs($this->admin)
            ->get(route('admin.reports.monthly-vouchers.export', [
                'orientation' => 'site_dump',
                'route' => $this->route->id,
                'year' => 2026,
                'month' => 9,
            ]))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('Plaka', $csv);
        $this->assertStringContainsString('34 AAA 01', $csv);
    }

    private function makeHaul(Vehicle $vehicle, string $hauledAt): Haul
    {
        return Haul::factory()->create([
            'vehicle_id' => $vehicle->id,
            'party_id' => $vehicle->currentOwnership->party_id,
            'route_id' => $this->route->id,
            'site_id' => $this->route->site_id,
            'dump_site_id' => $this->route->dump_site_id,
            'dump_party_id' => $this->route->dumpSite->party_id,
            'hauled_at' => $hauledAt,
        ]);
    }
}
