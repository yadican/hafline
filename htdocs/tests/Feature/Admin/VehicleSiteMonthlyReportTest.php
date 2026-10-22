<?php

namespace Tests\Feature\Admin;

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

class VehicleSiteMonthlyReportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Party $owner;

    private Vehicle $vehicle;

    private Vehicle $otherVehicle;

    private Site $siteA;

    private Site $siteB;

    private HaulRoute $routeA;

    private HaulRoute $routeB;

    private FuelStation $station;

    private DumpSite $dumpSite;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->yonetici()->create();
        $this->owner = Party::factory()->create();
        $this->dumpSite = DumpSite::factory()->create(['name' => '212']);

        $this->siteA = Site::factory()->create(['name' => 'BAKIRKÖY GÜL İNŞ']);
        $this->siteB = Site::factory()->create(['name' => 'TOPKAPI ALK']);

        $this->routeA = HaulRoute::factory()->create([
            'site_id' => $this->siteA->id,
            'dump_site_id' => $this->dumpSite->id,
        ]);
        $this->routeB = HaulRoute::factory()->create([
            'site_id' => $this->siteB->id,
            'dump_site_id' => $this->dumpSite->id,
        ]);

        $this->station = FuelStation::factory()->create([
            'name' => 'ALTUR',
            'unit_price' => 50.00,
        ]);
        $this->station->sites()->attach($this->siteA->id);

        $this->vehicle = Vehicle::factory()->ownedBy($this->owner, '2026-01-01')->create([
            'plate' => '34 ABR 69',
        ]);
        $this->otherVehicle = Vehicle::factory()->ownedBy($this->owner, '2026-01-01')->create([
            'plate' => '34 XYZ 01',
        ]);
    }

    public function test_guest_is_redirected_from_the_vehicle_site_monthly_report(): void
    {
        $this->get(route('admin.reports.vehicle-site-monthly'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_report_page_shows_filters_with_previous_month_defaults(): void
    {
        $previous = now()->subMonthNoOverflow();

        $this->actingAs($this->admin)
            ->get(route('admin.reports.vehicle-site-monthly'))
            ->assertOk()
            ->assertSee('Plaka şantiye döküm', false)
            ->assertSee('Raporu Göster', false)
            ->assertSee('Raporu görmek için seçim yapın.', false)
            ->assertSee((string) $previous->year, false);
    }

    public function test_report_builds_haul_and_fuel_rows_for_selected_site_only(): void
    {
        $this->makeHaul($this->vehicle, $this->routeA, '2026-09-05 08:00:00', 2000);
        $this->makeHaul($this->vehicle, $this->routeA, '2026-09-06 08:00:00', 2200);
        $this->makeHaul($this->vehicle, $this->routeB, '2026-09-07 08:00:00', 1800);
        $this->makeHaul($this->otherVehicle, $this->routeA, '2026-09-05 08:00:00', 9999);

        $this->makeReceipt($this->vehicle, '2026-09-10', 100, 50, siteId: $this->siteA->id);
        $this->makeReceipt($this->vehicle, '2026-09-12', 50, 52, siteId: $this->siteA->id);
        $this->makeReceipt($this->vehicle, '2026-09-13', 40, 50, siteId: $this->siteB->id);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.reports.vehicle-site-monthly', [
                'plate' => '34 ABR 69',
                'site' => $this->siteA->id,
                'year' => 2026,
                'month' => 9,
            ]));

        $response->assertOk()
            ->assertSee('Plaka Şantiye Bazlı Aylık Hareket Raporu', false)
            ->assertSee('34 ABR 69', false)
            ->assertSee('BAKIRKÖY GÜL İNŞ', false)
            ->assertSee('BAKIRKÖY GÜL İNŞ - 212', false)
            ->assertSee('ALTUR', false)
            ->assertDontSee('TOPKAPI ALK - 212', false);

        $report = $response->viewData('report');

        $this->assertSame(2, $report['trips']);
        $this->assertSame(150.0, $report['fuel_liters']);
        $this->assertSame(2, $report['fuel_receipts']);

        $hauls = collect($report['rows'])->where('type', 'haul')->values();
        $this->assertCount(1, $hauls);
        $this->assertSame(2.0, $hauls[0]['quantity']);
        $this->assertSame(4200.0, $hauls[0]['credit']);
        $this->assertEqualsWithDelta(2100.0, $hauls[0]['unit_price'], 0.0001);

        $fuel = collect($report['rows'])->firstWhere('type', 'fuel');
        $this->assertSame(150.0, $fuel['quantity']);
        $this->assertSame('lt', $fuel['quantity_unit']);
        $this->assertSame(7600.0, $fuel['debit']);

        $this->assertSame(4200.0, $report['total_credit']);
        $this->assertSame(7600.0, $report['total_debit']);
        $this->assertSame(-3400.0, $report['balance']);
    }

    public function test_untagged_fuel_from_station_linked_to_site_is_included(): void
    {
        $this->makeReceipt($this->vehicle, '2026-09-10', 80, 50);

        $report = $this->actingAs($this->admin)
            ->get(route('admin.reports.vehicle-site-monthly', [
                'plate' => '34 ABR 69',
                'site' => $this->siteA->id,
                'year' => 2026,
                'month' => 9,
            ]))
            ->assertOk()
            ->viewData('report');

        $this->assertSame(80.0, $report['fuel_liters']);
        $this->assertSame(4000.0, $report['total_debit']);
    }

    public function test_untagged_fuel_from_unlinked_station_is_excluded(): void
    {
        $otherStation = FuelStation::factory()->create(['name' => 'DIGER']);
        FuelReceipt::factory()->create([
            'vehicle_id' => $this->vehicle->id,
            'party_id' => $this->owner->id,
            'fuel_station_id' => $otherStation->id,
            'fuel_party_id' => $otherStation->party_id,
            'site_id' => null,
            'quantity' => 80,
            'unit_price' => 50,
            'issued_on' => '2026-09-10',
        ]);

        $report = $this->actingAs($this->admin)
            ->get(route('admin.reports.vehicle-site-monthly', [
                'plate' => '34 ABR 69',
                'site' => $this->siteA->id,
                'year' => 2026,
                'month' => 9,
            ]))
            ->assertOk()
            ->viewData('report');

        $this->assertSame(0.0, $report['fuel_liters']);
        $this->assertSame(0.0, $report['total_debit']);
    }

    public function test_entries_outside_the_month_are_excluded(): void
    {
        $this->makeHaul($this->vehicle, $this->routeA, '2026-08-31 23:00:00', 1000);
        $this->makeHaul($this->vehicle, $this->routeA, '2026-10-01 00:00:00', 1000);
        $this->makeHaul($this->vehicle, $this->routeA, '2026-09-15 10:00:00', 1500);

        $this->makeReceipt($this->vehicle, '2026-08-31', 40, 50, siteId: $this->siteA->id);
        $this->makeReceipt($this->vehicle, '2026-10-01', 40, 50, siteId: $this->siteA->id);
        $this->makeReceipt($this->vehicle, '2026-09-15', 80, 50, siteId: $this->siteA->id);

        $report = $this->actingAs($this->admin)
            ->get(route('admin.reports.vehicle-site-monthly', [
                'plate' => '34 ABR 69',
                'site' => $this->siteA->id,
                'year' => 2026,
                'month' => 9,
            ]))
            ->assertOk()
            ->viewData('report');

        $this->assertSame(1, $report['trips']);
        $this->assertSame(80.0, $report['fuel_liters']);
        $this->assertSame(1500.0, $report['total_credit']);
        $this->assertSame(4000.0, $report['total_debit']);
    }

    public function test_date_range_filters_entries_inclusively(): void
    {
        $this->makeHaul($this->vehicle, $this->routeA, '2026-09-01 00:00:00', 1000);
        $this->makeHaul($this->vehicle, $this->routeA, '2026-09-10 12:00:00', 1200);
        $this->makeHaul($this->vehicle, $this->routeA, '2026-09-15 23:30:00', 1300);
        $this->makeHaul($this->vehicle, $this->routeA, '2026-09-16 00:00:00', 9999);

        $this->makeReceipt($this->vehicle, '2026-09-01', 40, 50, siteId: $this->siteA->id);
        $this->makeReceipt($this->vehicle, '2026-09-15', 60, 50, siteId: $this->siteA->id);
        $this->makeReceipt($this->vehicle, '2026-09-16', 100, 50, siteId: $this->siteA->id);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.reports.vehicle-site-monthly', [
                'plate' => '34 ABR 69',
                'site' => $this->siteA->id,
                'period' => 'range',
                'from' => '2026-09-01',
                'to' => '2026-09-15',
            ]));

        $response->assertOk()
            ->assertSee('01.09.2026 – 15.09.2026', false);

        $report = $response->viewData('report');

        $this->assertSame('range', $report['period']);
        $this->assertSame(3, $report['trips']);
        $this->assertSame(100.0, $report['fuel_liters']);
        $this->assertSame(3500.0, $report['total_credit']);
        $this->assertSame(5000.0, $report['total_debit']);
    }

    public function test_soft_deleted_rows_are_excluded(): void
    {
        $haul = $this->makeHaul($this->vehicle, $this->routeA, '2026-09-05 08:00:00', 2000);
        $haul->delete();
        $this->makeHaul($this->vehicle, $this->routeA, '2026-09-06 08:00:00', 1800);

        $receipt = $this->makeReceipt($this->vehicle, '2026-09-10', 100, 50, siteId: $this->siteA->id);
        $receipt->delete();
        $this->makeReceipt($this->vehicle, '2026-09-11', 60, 50, siteId: $this->siteA->id);

        $report = $this->actingAs($this->admin)
            ->get(route('admin.reports.vehicle-site-monthly', [
                'plate' => '34 ABR 69',
                'site' => $this->siteA->id,
                'year' => 2026,
                'month' => 9,
            ]))
            ->assertOk()
            ->viewData('report');

        $this->assertSame(1, $report['trips']);
        $this->assertSame(60.0, $report['fuel_liters']);
        $this->assertSame(1800.0, $report['total_credit']);
        $this->assertSame(3000.0, $report['total_debit']);
    }

    public function test_csv_export_contains_report_rows(): void
    {
        $this->makeHaul($this->vehicle, $this->routeA, '2026-09-05 08:00:00', 2000);
        $this->makeReceipt($this->vehicle, '2026-09-10', 100, 50, siteId: $this->siteA->id);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.reports.vehicle-site-monthly.export', [
                'plate' => '34 ABR 69',
                'site' => $this->siteA->id,
                'year' => 2026,
                'month' => 9,
            ]));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));

        $csv = $response->streamedContent();

        $this->assertStringContainsString('Açıklama', $csv);
        $this->assertStringContainsString('BAKIRKÖY GÜL İNŞ - 212', $csv);
        $this->assertStringContainsString('ALTUR', $csv);
        $this->assertStringContainsString('Bakiye', $csv);
    }

    public function test_export_requires_plate_and_site(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.reports.vehicle-site-monthly.export', [
                'year' => 2026,
                'month' => 9,
            ]))
            ->assertRedirect(route('admin.reports.vehicle-site-monthly'))
            ->assertSessionHasErrors(['plate', 'site']);
    }

    public function test_unknown_plate_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.reports.vehicle-site-monthly', [
                'plate' => '99 UNKNOWN',
                'site' => $this->siteA->id,
                'year' => 2026,
                'month' => 9,
            ]))
            ->assertRedirect(route('admin.reports.vehicle-site-monthly'))
            ->assertSessionHasErrors('plate');
    }

    private function makeHaul(Vehicle $vehicle, HaulRoute $route, string $hauledAt, float $price): Haul
    {
        return Haul::factory()->create([
            'vehicle_id' => $vehicle->id,
            'party_id' => $vehicle->currentOwnership->party_id,
            'route_id' => $route->id,
            'site_id' => $route->site_id,
            'dump_site_id' => $route->dump_site_id,
            'dump_party_id' => $route->dumpSite->party_id,
            'price' => $price,
            'hauled_at' => $hauledAt,
        ]);
    }

    private function makeReceipt(
        Vehicle $vehicle,
        string $issuedOn,
        float $quantity,
        float $unitPrice,
        int $haulTicketCount = 2,
        ?string $siteId = null,
    ): FuelReceipt {
        return FuelReceipt::factory()->create([
            'vehicle_id' => $vehicle->id,
            'party_id' => $this->owner->id,
            'fuel_station_id' => $this->station->id,
            'fuel_party_id' => $this->station->party_id,
            'site_id' => $siteId,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'issued_on' => $issuedOn,
            'haul_ticket_count' => $haulTicketCount,
        ]);
    }
}
