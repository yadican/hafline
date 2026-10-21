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
use App\Models\VehiclePayment;
use App\Models\VehicleReceivable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class VehicleBalanceReportTest extends TestCase
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

    private FuelStation $stationA;

    private FuelStation $stationB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
        $this->owner = Party::factory()->create();
        $dumpSite = DumpSite::factory()->create(['name' => '212']);

        $this->siteA = Site::factory()->create(['name' => 'BAKIRKÖY GÜL İNŞ']);
        $this->siteB = Site::factory()->create(['name' => 'TOPKAPI ALK']);

        $this->routeA = HaulRoute::factory()->create([
            'site_id' => $this->siteA->id,
            'dump_site_id' => $dumpSite->id,
        ]);
        $this->routeB = HaulRoute::factory()->create([
            'site_id' => $this->siteB->id,
            'dump_site_id' => $dumpSite->id,
        ]);

        $this->stationA = FuelStation::factory()->create([
            'name' => 'ALTUR',
            'unit_price' => 50.00,
        ]);
        $this->stationB = FuelStation::factory()->create([
            'name' => 'BAŞAK PETROL',
            'unit_price' => 52.00,
        ]);

        $this->vehicle = Vehicle::factory()->ownedBy($this->owner, '2026-01-01')->create([
            'plate' => '34 ABR 69',
        ]);
        $this->otherVehicle = Vehicle::factory()->ownedBy($this->owner, '2026-01-01')->create([
            'plate' => '34 XYZ 01',
        ]);
    }

    public function test_guest_is_redirected_from_the_vehicle_balance_report(): void
    {
        $this->get(route('admin.reports.vehicle-balance'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_report_page_shows_filters_with_previous_month_defaults_without_running(): void
    {
        $previous = now()->subMonthNoOverflow();

        $this->actingAs($this->admin)
            ->get(route('admin.reports.vehicle-balance'))
            ->assertOk()
            ->assertSee('Plaka bakiye', false)
            ->assertSee('Raporu Göster', false)
            ->assertSee('Raporu görmek için seçim yapın.', false)
            ->assertSee((string) $previous->year, false)
            ->assertViewHas('ready', false);
    }

    public function test_report_builds_credit_cash_debit_and_balance_per_plate(): void
    {
        $this->makeHaul($this->vehicle, $this->routeA, '2026-09-05 08:00:00', 2000);
        $this->makeHaul($this->vehicle, $this->routeA, '2026-09-06 08:00:00', 2200);
        $this->makeHaul($this->otherVehicle, $this->routeA, '2026-09-05 08:00:00', 1000);

        $this->makeReceipt($this->vehicle, $this->stationA, '2026-09-10', 100, 50);
        $this->makeReceipt($this->otherVehicle, $this->stationA, '2026-09-11', 40, 50);

        VehiclePayment::query()->create([
            'vehicle_id' => $this->vehicle->id,
            'party_id' => $this->owner->id,
            'amount' => 1500,
            'occurred_on' => '2026-09-15',
            'description' => 'Avans',
        ]);

        VehicleReceivable::query()->create([
            'vehicle_id' => $this->vehicle->id,
            'party_id' => $this->owner->id,
            'amount' => 800,
            'occurred_on' => '2026-09-20',
            'description' => 'Elden',
        ]);

        $this->makePaidFine($this->vehicle, '2026-09-18', 250);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.reports.vehicle-balance', [
                'period' => 'month',
                'year' => 2026,
                'month' => 9,
            ]));

        $response->assertOk()
            ->assertSee('Plaka Bakiye Raporu', false)
            ->assertSee('34 ABR 69', false)
            ->assertSee('34 XYZ 01', false);

        $report = $response->viewData('report');

        $this->assertFalse($report['filtered']);
        $this->assertSame('Bakiye', $report['balance_label']);
        $this->assertCount(2, $report['rows']);

        $first = collect($report['rows'])->firstWhere('plate', '34 ABR 69');
        $this->assertSame(4200.0, $first['credit']);
        $this->assertSame(800.0, $first['cash']);
        // fuel 5000 + payment 1500 + fine 250
        $this->assertSame(6750.0, $first['debit']);
        // debit - (credit + cash) = 6750 - 5000 = 1750
        $this->assertSame(1750.0, $first['balance']);

        $second = collect($report['rows'])->firstWhere('plate', '34 XYZ 01');
        $this->assertSame(1000.0, $second['credit']);
        $this->assertSame(0.0, $second['cash']);
        $this->assertSame(2000.0, $second['debit']);
        $this->assertSame(1000.0, $second['balance']);

        $this->assertSame(5200.0, $report['total_credit']);
        $this->assertSame(800.0, $report['total_cash']);
        $this->assertSame(8750.0, $report['total_debit']);
        $this->assertSame(2750.0, $report['total_balance']);
    }

    public function test_site_filter_limits_hauls_and_fuel_and_labels_filtered_net(): void
    {
        $this->makeHaul($this->vehicle, $this->routeA, '2026-09-05 08:00:00', 2000);
        $this->makeHaul($this->vehicle, $this->routeB, '2026-09-06 08:00:00', 3000);

        $this->makeReceipt($this->vehicle, $this->stationA, '2026-09-10', 100, 50, $this->siteA->id);
        $this->makeReceipt($this->vehicle, $this->stationA, '2026-09-11', 40, 50, $this->siteB->id);

        VehicleReceivable::query()->create([
            'vehicle_id' => $this->vehicle->id,
            'party_id' => $this->owner->id,
            'amount' => 500,
            'occurred_on' => '2026-09-20',
        ]);

        $report = $this->actingAs($this->admin)
            ->get(route('admin.reports.vehicle-balance', [
                'period' => 'month',
                'year' => 2026,
                'month' => 9,
                'site' => $this->siteA->id,
            ]))
            ->assertOk()
            ->assertSee('Filtreli net', false)
            ->viewData('report');

        $this->assertTrue($report['filtered']);
        $this->assertSame('Filtreli net', $report['balance_label']);

        $row = $report['rows'][0];
        $this->assertSame(2000.0, $row['credit']);
        $this->assertSame(500.0, $row['cash']);
        $this->assertSame(5000.0, $row['debit']);
        $this->assertSame(2500.0, $row['balance']);
    }

    public function test_fuel_station_filter_limits_fuel_debit(): void
    {
        $this->makeHaul($this->vehicle, $this->routeA, '2026-09-05 08:00:00', 1000);
        $this->makeReceipt($this->vehicle, $this->stationA, '2026-09-10', 100, 50);
        $this->makeReceipt($this->vehicle, $this->stationB, '2026-09-11', 80, 50);

        $report = $this->actingAs($this->admin)
            ->get(route('admin.reports.vehicle-balance', [
                'period' => 'month',
                'year' => 2026,
                'month' => 9,
                'fuel_station' => $this->stationA->id,
            ]))
            ->assertOk()
            ->viewData('report');

        $this->assertTrue($report['filtered']);
        $row = $report['rows'][0];
        $this->assertSame(1000.0, $row['credit']);
        $this->assertSame(5000.0, $row['debit']);
        $this->assertSame(4000.0, $row['balance']);
    }

    public function test_entries_outside_the_month_are_excluded(): void
    {
        $this->makeHaul($this->vehicle, $this->routeA, '2026-08-31 23:00:00', 1000);
        $this->makeHaul($this->vehicle, $this->routeA, '2026-10-01 00:00:00', 1000);
        $this->makeHaul($this->vehicle, $this->routeA, '2026-09-15 10:00:00', 1500);

        $this->makeReceipt($this->vehicle, $this->stationA, '2026-08-31', 40, 50);
        $this->makeReceipt($this->vehicle, $this->stationA, '2026-10-01', 40, 50);
        $this->makeReceipt($this->vehicle, $this->stationA, '2026-09-15', 80, 50);

        $report = $this->actingAs($this->admin)
            ->get(route('admin.reports.vehicle-balance', [
                'period' => 'month',
                'year' => 2026,
                'month' => 9,
            ]))
            ->assertOk()
            ->viewData('report');

        $row = $report['rows'][0];
        $this->assertSame(1500.0, $row['credit']);
        $this->assertSame(4000.0, $row['debit']);
    }

    public function test_date_range_includes_end_day(): void
    {
        $this->makeHaul($this->vehicle, $this->routeA, '2026-09-01 00:00:00', 1000);
        $this->makeHaul($this->vehicle, $this->routeA, '2026-09-15 23:30:00', 1300);
        $this->makeHaul($this->vehicle, $this->routeA, '2026-09-16 00:00:00', 9999);

        $this->makeReceipt($this->vehicle, $this->stationA, '2026-09-01', 40, 50);
        $this->makeReceipt($this->vehicle, $this->stationA, '2026-09-15', 60, 50);
        $this->makeReceipt($this->vehicle, $this->stationA, '2026-09-16', 100, 50);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.reports.vehicle-balance', [
                'period' => 'range',
                'from' => '2026-09-01',
                'to' => '2026-09-15',
            ]));

        $response->assertOk()
            ->assertSee('01.09.2026 – 15.09.2026', false);

        $report = $response->viewData('report');
        $row = $report['rows'][0];

        $this->assertSame('range', $report['period']);
        $this->assertSame(2300.0, $row['credit']);
        $this->assertSame(5000.0, $row['debit']);
    }

    public function test_all_period_includes_every_date(): void
    {
        $this->makeHaul($this->vehicle, $this->routeA, '2025-01-01 08:00:00', 1000);
        $this->makeHaul($this->vehicle, $this->routeA, '2026-12-31 08:00:00', 2000);
        $this->makeReceipt($this->vehicle, $this->stationA, '2026-06-15', 50, 40);

        $report = $this->actingAs($this->admin)
            ->get(route('admin.reports.vehicle-balance', [
                'period' => 'all',
            ]))
            ->assertOk()
            ->assertSee('Tüm zamanlar', false)
            ->viewData('report');

        $row = $report['rows'][0];
        $this->assertSame('all', $report['period']);
        $this->assertSame(3000.0, $row['credit']);
        $this->assertSame(2000.0, $row['debit']);
    }

    public function test_soft_deleted_rows_are_excluded(): void
    {
        $haul = $this->makeHaul($this->vehicle, $this->routeA, '2026-09-05 08:00:00', 2000);
        $haul->delete();
        $this->makeHaul($this->vehicle, $this->routeA, '2026-09-06 08:00:00', 1800);

        $receipt = $this->makeReceipt($this->vehicle, $this->stationA, '2026-09-10', 100, 50);
        $receipt->delete();
        $this->makeReceipt($this->vehicle, $this->stationA, '2026-09-11', 60, 50);

        $report = $this->actingAs($this->admin)
            ->get(route('admin.reports.vehicle-balance', [
                'period' => 'month',
                'year' => 2026,
                'month' => 9,
            ]))
            ->assertOk()
            ->viewData('report');

        $row = $report['rows'][0];
        $this->assertSame(1800.0, $row['credit']);
        $this->assertSame(3000.0, $row['debit']);
    }

    public function test_inactive_vehicle_with_activity_is_included(): void
    {
        $this->vehicle->update(['active' => false]);
        $this->makeHaul($this->vehicle, $this->routeA, '2026-09-05 08:00:00', 1500);

        $report = $this->actingAs($this->admin)
            ->get(route('admin.reports.vehicle-balance', [
                'period' => 'month',
                'year' => 2026,
                'month' => 9,
            ]))
            ->assertOk()
            ->viewData('report');

        $this->assertCount(1, $report['rows']);
        $this->assertSame('34 ABR 69', $report['rows'][0]['plate']);
        $this->assertSame(1500.0, $report['rows'][0]['credit']);
    }

    public function test_zero_activity_vehicles_are_hidden(): void
    {
        $this->makeHaul($this->vehicle, $this->routeA, '2026-09-05 08:00:00', 1000);

        $report = $this->actingAs($this->admin)
            ->get(route('admin.reports.vehicle-balance', [
                'period' => 'month',
                'year' => 2026,
                'month' => 9,
            ]))
            ->assertOk()
            ->viewData('report');

        $this->assertCount(1, $report['rows']);
        $this->assertSame('34 ABR 69', $report['rows'][0]['plate']);
    }

    public function test_csv_export_contains_report_rows(): void
    {
        $this->makeHaul($this->vehicle, $this->routeA, '2026-09-05 08:00:00', 2000);
        $this->makeReceipt($this->vehicle, $this->stationA, '2026-09-10', 100, 50);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.reports.vehicle-balance.export', [
                'period' => 'month',
                'year' => 2026,
                'month' => 9,
            ]));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));

        $csv = $response->streamedContent();

        $this->assertStringContainsString('Plaka', $csv);
        $this->assertStringContainsString('Alacak', $csv);
        $this->assertStringContainsString('34 ABR 69', $csv);
        $this->assertStringContainsString('Bakiye', $csv);
        $this->assertStringContainsString('Toplam', $csv);
    }

    public function test_invalid_site_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.reports.vehicle-balance', [
                'period' => 'month',
                'year' => 2026,
                'month' => 9,
                'site' => 'not-a-uuid',
            ]))
            ->assertRedirect(route('admin.reports.vehicle-balance'))
            ->assertSessionHasErrors('site');
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
        FuelStation $station,
        string $issuedOn,
        float $quantity,
        float $unitPrice,
        ?string $siteId = null,
    ): FuelReceipt {
        return FuelReceipt::factory()->create([
            'vehicle_id' => $vehicle->id,
            'party_id' => $this->owner->id,
            'fuel_station_id' => $station->id,
            'fuel_party_id' => $station->party_id,
            'site_id' => $siteId,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'issued_on' => $issuedOn,
        ]);
    }

    private function makePaidFine(Vehicle $vehicle, string $paidOn, float $amount): void
    {
        DB::table('vehicle_fines')->insert([
            'id' => (string) Str::uuid(),
            'vehicle_id' => $vehicle->id,
            'party_id' => $this->owner->id,
            'amount' => $amount,
            'occurred_on' => $paidOn,
            'status' => 'paid',
            'paid_on' => $paidOn,
            'description' => 'Ceza',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
