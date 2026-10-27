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

        $this->admin = User::factory()->yonetici()->create();
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

    public function test_report_page_shows_filters_without_running(): void
    {
        $previous = now()->subMonthNoOverflow();

        $this->actingAs($this->admin)
            ->get(route('admin.reports.vehicle-balance'))
            ->assertOk()
            ->assertSee('Plaka bakiye', false)
            ->assertSee('Şantiye, yakıt yeri ve dönemi seçip Raporu Göster’e basın.', false)
            ->assertSee((string) $previous->year, false)
            ->assertViewHas('ready', false);
    }

    public function test_report_requires_site_and_fuel_station(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.reports.vehicle-balance', [
                'period' => 'month',
                'year' => 2026,
                'month' => 9,
            ]))
            ->assertRedirect(route('admin.reports.vehicle-balance'))
            ->assertSessionHasErrors(['site', 'fuel_station']);
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
            ->get(route('admin.reports.vehicle-balance', $this->filters()));

        $response->assertOk()
            ->assertSee('Plaka Bakiye Raporu', false)
            ->assertSee('34 ABR 69', false)
            ->assertSee('34 XYZ 01', false)
            ->assertSee('Filtreli bakiye', false);

        $report = $response->viewData('report');

        $this->assertTrue($report['filtered']);
        $this->assertCount(2, $report['rows']);

        $first = collect($report['rows'])->firstWhere('plate', '34 ABR 69');
        $this->assertSame(4200.0, $first['credit']);
        $this->assertSame(800.0, $first['cash']);
        $this->assertSame(6750.0, $first['debit']);
        $this->assertSame(1750.0, $first['balance']);

        $second = collect($report['rows'])->firstWhere('plate', '34 XYZ 01');
        $this->assertSame(1000.0, $second['credit']);
        $this->assertSame(2000.0, $second['debit']);
        $this->assertSame(1000.0, $second['balance']);
    }

    public function test_site_limits_haul_credit_while_fuel_station_limits_fuel_debit(): void
    {
        $this->makeHaul($this->vehicle, $this->routeA, '2026-09-05 08:00:00', 2000);
        $this->makeHaul($this->vehicle, $this->routeB, '2026-09-06 08:00:00', 3000);
        $this->makeReceipt($this->vehicle, $this->stationA, '2026-09-10', 100, 50);
        $this->makeReceipt($this->vehicle, $this->stationB, '2026-09-11', 40, 50);

        $report = $this->actingAs($this->admin)
            ->get(route('admin.reports.vehicle-balance', $this->filters()))
            ->assertOk()
            ->viewData('report');

        $row = $report['rows'][0];
        $this->assertSame(2000.0, $row['credit']);
        $this->assertSame(5000.0, $row['debit']);
    }

    public function test_hides_plates_missing_site_haul_or_station_fuel(): void
    {
        $this->makeHaul($this->vehicle, $this->routeA, '2026-09-05 08:00:00', 1500);
        $this->makeReceipt($this->vehicle, $this->stationA, '2026-09-10', 40, 50);

        $this->makeHaul($this->otherVehicle, $this->routeB, '2026-09-05 08:00:00', 3000);
        $this->makeReceipt($this->otherVehicle, $this->stationA, '2026-09-11', 40, 50);

        $report = $this->actingAs($this->admin)
            ->get(route('admin.reports.vehicle-balance', $this->filters()))
            ->assertOk()
            ->viewData('report');

        $this->assertCount(1, $report['rows']);
        $this->assertSame('34 ABR 69', $report['rows'][0]['plate']);
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
            ->get(route('admin.reports.vehicle-balance', $this->filters()))
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
            ->get(route('admin.reports.vehicle-balance', $this->filters([
                'period' => 'range',
                'from' => '2026-09-01',
                'to' => '2026-09-15',
            ])));

        $response->assertOk()
            ->assertSee('01.09.2026 – 15.09.2026', false);

        $report = $response->viewData('report');
        $this->assertSame(2300.0, $report['rows'][0]['credit']);
        $this->assertSame(5000.0, $report['rows'][0]['debit']);
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
            ->get(route('admin.reports.vehicle-balance', $this->filters()))
            ->assertOk()
            ->viewData('report');

        $row = $report['rows'][0];
        $this->assertSame(1800.0, $row['credit']);
        $this->assertSame(3000.0, $row['debit']);
    }

    public function test_csv_export_contains_report_rows(): void
    {
        $this->makeHaul($this->vehicle, $this->routeA, '2026-09-05 08:00:00', 2000);
        $this->makeReceipt($this->vehicle, $this->stationA, '2026-09-10', 100, 50);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.reports.vehicle-balance.export', $this->filters()));

        $response->assertOk();
        $csv = $response->streamedContent();

        $this->assertStringContainsString('34 ABR 69', $csv);
        $this->assertStringContainsString('Filtreli bakiye', $csv);
    }

    public function test_invalid_site_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.reports.vehicle-balance', $this->filters([
                'site' => 'not-a-uuid',
            ])))
            ->assertRedirect(route('admin.reports.vehicle-balance'))
            ->assertSessionHasErrors('site');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public function test_balance_carries_the_previous_periods_balance_and_keeps_rows_with_only_a_carryover(): void
    {
        $this->makeHaul($this->vehicle, $this->routeA, '2026-08-20 08:00:00', 2000);
        $this->makeReceipt($this->vehicle, $this->stationA, '2026-08-21', 10, 50);
        $this->makeHaul($this->otherVehicle, $this->routeA, '2026-08-22 08:00:00', 700);
        $this->makeHaul($this->vehicle, $this->routeA, '2026-09-05 08:00:00', 1000);

        $report = fn (int $month) => collect($this->actingAs($this->admin)
            ->get(route('admin.reports.vehicle-balance', $this->filters(['month' => $month])))
            ->assertOk()
            ->viewData('report')['rows'])->keyBy('plate');

        $august = $report(8);
        $september = $report(9);

        $this->assertSame(-1500.0, $august['34 ABR 69']['balance']);
        $this->assertSame($august['34 ABR 69']['balance'], $september['34 ABR 69']['opening']);
        $this->assertSame(1000.0, $september['34 ABR 69']['credit']);
        $this->assertSame(-2500.0, $september['34 ABR 69']['balance']);

        $this->assertSame(-700.0, $september['34 XYZ 01']['opening'], 'Dönemde hareketi olmayan ama devri olan satır listede kalır.');
        $this->assertSame(0.0, $september['34 XYZ 01']['credit']);
        $this->assertSame(-700.0, $september['34 XYZ 01']['balance']);

        $this->actingAs($this->admin)
            ->get(route('admin.reports.vehicle-balance', $this->filters()))
            ->assertSeeInOrder(['Devir', 'Alacak', 'Elden', 'Borç', 'Filtreli bakiye']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function filters(array $overrides = []): array
    {
        return array_merge([
            'period' => 'month',
            'year' => 2026,
            'month' => 9,
            'site' => $this->siteA->id,
            'fuel_station' => $this->stationA->id,
        ], $overrides);
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
