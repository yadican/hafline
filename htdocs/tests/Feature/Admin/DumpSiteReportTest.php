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

class DumpSiteReportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private DumpSite $dumpSite;

    private HaulRoute $routeA;

    private HaulRoute $routeB;

    private VehicleClass $makine;

    private VehicleClass $tir;

    private Vehicle $vehicleA;

    private Vehicle $vehicleB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->yonetici()->create();
        $this->dumpSite = DumpSite::factory()->create(['name' => '212']);

        $siteA = Site::factory()->create(['name' => 'HASÜLGEN KARAYOLLARI']);
        $siteB = Site::factory()->create(['name' => 'PERPA']);

        $this->routeA = HaulRoute::factory()->create([
            'site_id' => $siteA->id,
            'dump_site_id' => $this->dumpSite->id,
        ]);
        $this->routeB = HaulRoute::factory()->create([
            'site_id' => $siteB->id,
            'dump_site_id' => $this->dumpSite->id,
        ]);

        $this->makine = VehicleClass::factory()->create(['name' => 'MAKİNE']);
        $this->tir = VehicleClass::factory()->create(['name' => 'TIR']);

        $this->vehicleA = Vehicle::factory()->create([
            'plate' => 'CASE 9021',
            'vehicle_class_id' => $this->makine->id,
        ]);
        $this->vehicleB = Vehicle::factory()->create([
            'plate' => 'KATO 1',
            'vehicle_class_id' => $this->tir->id,
        ]);
    }

    public function test_guest_is_redirected_from_the_dump_site_report(): void
    {
        $this->get(route('admin.reports.dump-sites'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_report_page_shows_filters_with_previous_month_defaults(): void
    {
        $previous = now()->subMonthNoOverflow();

        $this->actingAs($this->admin)
            ->get(route('admin.reports.dump-sites'))
            ->assertOk()
            ->assertSee('Döküm yeri raporları', false)
            ->assertSee('Raporu Göster', false)
            ->assertSee('Raporu görmek için seçim yapın.', false)
            ->assertSee((string) $previous->year, false);
    }

    public function test_matrix_counts_trips_by_plate_and_route(): void
    {
        $this->seedHauls();

        $response = $this->actingAs($this->admin)
            ->get(route('admin.reports.dump-sites', [
                'dump_site' => $this->dumpSite->id,
                'year' => 2026,
                'month' => 9,
            ]));

        $response->assertOk()
            ->assertSee('Dökümyeri Raporu', false)
            ->assertSee('212 2026 Eylül Raporu', false)
            ->assertSee('212 HASÜLGEN KARAYOLLARI', false)
            ->assertSee('212 PERPA', false)
            ->assertSee('CASE 9021', false)
            ->assertSee('KATO 1', false);

        $matrix = $response->viewData('matrix');

        $this->assertSame(3, $matrix['grand_total']);
        $this->assertCount(2, $matrix['columns']);
        $this->assertCount(2, $matrix['rows']);

        $rowA = collect($matrix['rows'])->firstWhere('plate', 'CASE 9021');
        $rowB = collect($matrix['rows'])->firstWhere('plate', 'KATO 1');

        $this->assertSame(2, $rowA['total']);
        $this->assertSame(1, $rowA['counts'][$this->routeA->id]);
        $this->assertSame(1, $rowA['counts'][$this->routeB->id]);
        $this->assertSame(1, $rowB['total']);
        $this->assertSame(1, $rowB['counts'][$this->routeA->id]);
        $this->assertArrayNotHasKey($this->routeB->id, $rowB['counts']);
    }

    public function test_vehicle_class_filter_excludes_other_classes(): void
    {
        $this->seedHauls();

        $matrix = $this->actingAs($this->admin)
            ->get(route('admin.reports.dump-sites', [
                'dump_site' => $this->dumpSite->id,
                'year' => 2026,
                'month' => 9,
                'class' => $this->makine->id,
            ]))
            ->assertOk()
            ->viewData('matrix');

        $this->assertCount(1, $matrix['rows']);
        $this->assertSame('CASE 9021', $matrix['rows'][0]['plate']);
        $this->assertSame(2, $matrix['grand_total']);
    }

    public function test_soft_deleted_hauls_are_excluded(): void
    {
        $haul = $this->makeHaul($this->vehicleA, $this->routeA, '2026-09-10 10:00:00');
        $haul->delete();

        $this->makeHaul($this->vehicleA, $this->routeA, '2026-09-11 10:00:00');

        $matrix = $this->actingAs($this->admin)
            ->get(route('admin.reports.dump-sites', [
                'dump_site' => $this->dumpSite->id,
                'year' => 2026,
                'month' => 9,
            ]))
            ->assertOk()
            ->viewData('matrix');

        $this->assertSame(1, $matrix['grand_total']);
    }

    public function test_hauls_outside_the_month_are_excluded(): void
    {
        $this->makeHaul($this->vehicleA, $this->routeA, '2026-08-31 23:00:00');
        $this->makeHaul($this->vehicleA, $this->routeA, '2026-10-01 00:00:00');
        $this->makeHaul($this->vehicleA, $this->routeA, '2026-09-15 12:00:00');

        $matrix = $this->actingAs($this->admin)
            ->get(route('admin.reports.dump-sites', [
                'dump_site' => $this->dumpSite->id,
                'year' => 2026,
                'month' => 9,
            ]))
            ->assertOk()
            ->viewData('matrix');

        $this->assertSame(1, $matrix['grand_total']);
    }

    public function test_date_range_filters_trips_inclusively(): void
    {
        $this->makeHaul($this->vehicleA, $this->routeA, '2026-09-01 00:00:00');
        $this->makeHaul($this->vehicleA, $this->routeA, '2026-09-10 12:00:00');
        $this->makeHaul($this->vehicleA, $this->routeA, '2026-09-15 23:30:00');
        $this->makeHaul($this->vehicleA, $this->routeA, '2026-09-16 00:00:00');

        $response = $this->actingAs($this->admin)
            ->get(route('admin.reports.dump-sites', [
                'dump_site' => $this->dumpSite->id,
                'period' => 'range',
                'from' => '2026-09-01',
                'to' => '2026-09-15',
            ]));

        $response->assertOk()
            ->assertSee('212 01.09.2026 – 15.09.2026 Raporu', false);

        $matrix = $response->viewData('matrix');

        $this->assertSame('range', $matrix['period']);
        $this->assertSame(3, $matrix['grand_total']);
    }

    public function test_date_range_defaults_to_previous_month_bounds(): void
    {
        $previous = now()->subMonthNoOverflow();

        $filters = $this->actingAs($this->admin)
            ->get(route('admin.reports.dump-sites', [
                'period' => 'range',
            ]))
            ->assertOk()
            ->viewData('filters');

        $this->assertSame('range', $filters['period']);
        $this->assertSame($previous->copy()->startOfMonth()->toDateString(), $filters['from']);
        $this->assertSame($previous->copy()->endOfMonth()->toDateString(), $filters['to']);
    }

    public function test_csv_export_contains_matrix_rows(): void
    {
        $this->seedHauls();

        $response = $this->actingAs($this->admin)
            ->get(route('admin.reports.dump-sites.export', [
                'dump_site' => $this->dumpSite->id,
                'year' => 2026,
                'month' => 9,
            ]));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));

        $csv = $response->streamedContent();

        $this->assertStringContainsString('Plaka', $csv);
        $this->assertStringContainsString('212 HASÜLGEN KARAYOLLARI', $csv);
        $this->assertStringContainsString('CASE 9021', $csv);
        $this->assertStringContainsString('Toplam', $csv);
    }

    public function test_export_requires_a_dump_site(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.reports.dump-sites.export', [
                'year' => 2026,
                'month' => 9,
            ]))
            ->assertRedirect(route('admin.reports.dump-sites'))
            ->assertSessionHasErrors('dump_site');
    }

    private function seedHauls(): void
    {
        $this->makeHaul($this->vehicleA, $this->routeA, '2026-09-05 08:00:00');
        $this->makeHaul($this->vehicleA, $this->routeB, '2026-09-07 08:00:00');
        $this->makeHaul($this->vehicleB, $this->routeA, '2026-09-08 08:00:00');
    }

    private function makeHaul(Vehicle $vehicle, HaulRoute $route, string $hauledAt): Haul
    {
        return Haul::factory()->create([
            'vehicle_id' => $vehicle->id,
            'party_id' => $vehicle->currentOwnership->party_id,
            'route_id' => $route->id,
            'site_id' => $route->site_id,
            'dump_site_id' => $route->dump_site_id,
            'dump_party_id' => $route->dumpSite->party_id,
            'hauled_at' => $hauledAt,
        ]);
    }
}
