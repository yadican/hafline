<?php

namespace Tests\Feature\Admin;

use App\Enums\PartyRole;
use App\Enums\PaymentMethod;
use App\Models\CompanyTransaction;
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

class VehicleCostReportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Party $owner;

    private Party $otherOwner;

    private Party $marketFirm;

    private Vehicle $vehicleA;

    private Vehicle $vehicleB;

    private Vehicle $otherVehicle;

    private HaulRoute $route;

    private FuelStation $station;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->yonetici()->create();
        $this->owner = Party::factory()->create(['name' => 'Fahri Doğan']);
        $this->otherOwner = Party::factory()->create(['name' => 'Mehmet Demir']);
        $this->marketFirm = Party::factory()->company()->withRole(PartyRole::Market)->create([
            'name' => 'PİYASA A.Ş.',
        ]);

        $dumpSite = DumpSite::factory()->create(['name' => '212']);
        $site = Site::factory()->create(['name' => 'BAKIRKÖY']);
        $this->route = HaulRoute::factory()->create([
            'site_id' => $site->id,
            'dump_site_id' => $dumpSite->id,
        ]);
        $this->station = FuelStation::factory()->create([
            'name' => 'ALTUR',
            'unit_price' => 50.00,
        ]);

        $this->vehicleA = Vehicle::factory()->ownedBy($this->owner, '2026-01-01')->create([
            'plate' => '34 BR 2258',
        ]);
        $this->vehicleB = Vehicle::factory()->ownedBy($this->owner, '2026-01-01')->create([
            'plate' => '34 ZR 7555',
        ]);
        $this->otherVehicle = Vehicle::factory()->ownedBy($this->otherOwner, '2026-01-01')->create([
            'plate' => '06 ABC 01',
        ]);
    }

    public function test_guest_is_redirected_from_the_vehicle_cost_report(): void
    {
        $this->get(route('admin.reports.vehicle-cost'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_report_page_shows_filters_without_running(): void
    {
        $previous = now()->subMonthNoOverflow();

        $this->actingAs($this->admin)
            ->get(route('admin.reports.vehicle-cost'))
            ->assertOk()
            ->assertSee('Araç maliyet', false)
            ->assertSee('Raporu görmek için seçim yapın.', false)
            ->assertSee((string) $previous->year, false)
            ->assertViewHas('ready', false);
    }

    public function test_report_requires_owner_to_run(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.reports.vehicle-cost', [
                'period' => 'month',
                'year' => 2026,
                'month' => 9,
            ]))
            ->assertOk()
            ->assertViewHas('ready', false)
            ->assertSee('Raporu görmek için seçim yapın.', false);
    }

    public function test_export_requires_owner(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.reports.vehicle-cost.export', [
                'period' => 'month',
                'year' => 2026,
                'month' => 9,
            ]))
            ->assertRedirect(route('admin.reports.vehicle-cost'))
            ->assertSessionHasErrors('owner');
    }

    public function test_report_builds_gelir_yakit_piyasa_and_durum_per_plate(): void
    {
        $this->makeHaul($this->vehicleA, $this->owner, '2026-09-05 08:00:00', 20000);
        $this->makeHaul($this->vehicleA, $this->owner, '2026-09-06 08:00:00', 20590);
        $this->makeHaul($this->vehicleB, $this->owner, '2026-09-07 08:00:00', 48704);
        $this->makeHaul($this->otherVehicle, $this->otherOwner, '2026-09-05 08:00:00', 99999);

        $this->makeReceipt($this->vehicleA, $this->owner, '2026-09-10', 100, 50); // 5000
        $this->makeReceipt($this->vehicleB, $this->owner, '2026-09-11', 200, 50); // 10000
        $this->makeReceipt($this->otherVehicle, $this->otherOwner, '2026-09-10', 80, 50);

        $this->makeMarket($this->vehicleA, $this->owner, '2026-09-12', credit: 3000);
        $this->makeMarket($this->vehicleA, $this->owner, '2026-09-13', debit: 1500);
        $this->makeMarket($this->vehicleB, $this->owner, '2026-09-14', credit: 8000);
        $this->makeMarket($this->otherVehicle, $this->otherOwner, '2026-09-12', credit: 5000);

        $report = $this->actingAs($this->admin)
            ->get(route('admin.reports.vehicle-cost', $this->filters()))
            ->assertOk()
            ->assertSee('Araç Maliyet Raporu', false)
            ->assertSee('Fahri Doğan', false)
            ->assertSee('34 BR 2258', false)
            ->assertSee('34 ZR 7555', false)
            ->assertDontSee('06 ABC 01', false)
            ->viewData('report');

        $this->assertCount(2, $report['rows']);
        $this->assertSame(2, $report['vehicle_count']);

        $byPlate = collect($report['rows'])->keyBy('plate');

        $this->assertSame(40590.0, $byPlate['34 BR 2258']['gelir']);
        $this->assertSame(5000.0, $byPlate['34 BR 2258']['yakit']);
        $this->assertSame(4500.0, $byPlate['34 BR 2258']['piyasa']);
        $this->assertSame(31090.0, $byPlate['34 BR 2258']['durum']);

        $this->assertSame(48704.0, $byPlate['34 ZR 7555']['gelir']);
        $this->assertSame(10000.0, $byPlate['34 ZR 7555']['yakit']);
        $this->assertSame(8000.0, $byPlate['34 ZR 7555']['piyasa']);
        $this->assertSame(30704.0, $byPlate['34 ZR 7555']['durum']);

        $this->assertSame(89294.0, $report['total_gelir']);
        $this->assertSame(15000.0, $report['total_yakit']);
        $this->assertSame(12500.0, $report['total_piyasa']);
        $this->assertSame(61794.0, $report['total_durum']);
    }

    public function test_snapshot_owner_keeps_activity_with_prior_owner(): void
    {
        $this->makeHaul($this->vehicleA, $this->owner, '2026-09-05 08:00:00', 3000);
        $this->makeReceipt($this->vehicleA, $this->owner, '2026-09-06', 20, 50);
        $this->makeMarket($this->vehicleA, $this->owner, '2026-09-07', credit: 400);

        $this->makeHaul($this->vehicleA, $this->otherOwner, '2026-09-10 08:00:00', 1500);
        $this->makeReceipt($this->vehicleA, $this->otherOwner, '2026-09-11', 10, 50);
        $this->makeMarket($this->vehicleA, $this->otherOwner, '2026-09-12', credit: 200);

        $ownerReport = $this->actingAs($this->admin)
            ->get(route('admin.reports.vehicle-cost', $this->filters()))
            ->assertOk()
            ->viewData('report');

        $this->assertCount(1, $ownerReport['rows']);
        $this->assertSame(3000.0, $ownerReport['rows'][0]['gelir']);
        $this->assertSame(1000.0, $ownerReport['rows'][0]['yakit']);
        $this->assertSame(400.0, $ownerReport['rows'][0]['piyasa']);
        $this->assertSame(1600.0, $ownerReport['rows'][0]['durum']);

        $otherReport = $this->actingAs($this->admin)
            ->get(route('admin.reports.vehicle-cost', $this->filters([
                'owner' => $this->otherOwner->id,
            ])))
            ->assertOk()
            ->viewData('report');

        $this->assertCount(1, $otherReport['rows']);
        $this->assertSame(1500.0, $otherReport['rows'][0]['gelir']);
        $this->assertSame(500.0, $otherReport['rows'][0]['yakit']);
        $this->assertSame(200.0, $otherReport['rows'][0]['piyasa']);
    }

    public function test_date_bounds_are_half_open_and_inclusive_end_day(): void
    {
        $this->makeHaul($this->vehicleA, $this->owner, '2026-08-31 23:59:59', 100);
        $this->makeHaul($this->vehicleA, $this->owner, '2026-09-01 00:00:00', 200);
        $this->makeHaul($this->vehicleA, $this->owner, '2026-09-30 23:00:00', 300);
        $this->makeHaul($this->vehicleA, $this->owner, '2026-10-01 00:00:00', 400);

        $this->makeReceipt($this->vehicleA, $this->owner, '2026-08-31', 1, 50);
        $this->makeReceipt($this->vehicleA, $this->owner, '2026-09-01', 2, 50);
        $this->makeReceipt($this->vehicleA, $this->owner, '2026-09-30', 3, 50);
        $this->makeReceipt($this->vehicleA, $this->owner, '2026-10-01', 4, 50);

        $this->makeMarket($this->vehicleA, $this->owner, '2026-08-31', credit: 10);
        $this->makeMarket($this->vehicleA, $this->owner, '2026-09-01', credit: 20);
        $this->makeMarket($this->vehicleA, $this->owner, '2026-09-30', credit: 30);
        $this->makeMarket($this->vehicleA, $this->owner, '2026-10-01', credit: 40);

        $report = $this->actingAs($this->admin)
            ->get(route('admin.reports.vehicle-cost', $this->filters()))
            ->assertOk()
            ->viewData('report');

        $this->assertSame(500.0, $report['rows'][0]['gelir']);
        $this->assertSame(250.0, $report['rows'][0]['yakit']);
        $this->assertSame(50.0, $report['rows'][0]['piyasa']);
    }

    public function test_hides_vehicles_with_no_cost_activity_for_owner(): void
    {
        $this->makeHaul($this->vehicleA, $this->owner, '2026-09-05 08:00:00', 1000);
        $this->makeReceipt($this->vehicleA, $this->owner, '2026-09-06', 10, 50);

        $report = $this->actingAs($this->admin)
            ->get(route('admin.reports.vehicle-cost', $this->filters()))
            ->assertOk()
            ->viewData('report');

        $this->assertCount(1, $report['rows']);
        $this->assertSame('34 BR 2258', $report['rows'][0]['plate']);
    }

    public function test_soft_deleted_rows_are_excluded(): void
    {
        $haul = $this->makeHaul($this->vehicleA, $this->owner, '2026-09-05 08:00:00', 5000);
        $haul->delete();
        $this->makeHaul($this->vehicleA, $this->owner, '2026-09-06 08:00:00', 1000);

        $receipt = $this->makeReceipt($this->vehicleA, $this->owner, '2026-09-10', 100, 50);
        $receipt->delete();
        $this->makeReceipt($this->vehicleA, $this->owner, '2026-09-11', 20, 50);

        $market = $this->makeMarket($this->vehicleA, $this->owner, '2026-09-12', credit: 900);
        $market->delete();
        $this->makeMarket($this->vehicleA, $this->owner, '2026-09-13', credit: 100);

        $report = $this->actingAs($this->admin)
            ->get(route('admin.reports.vehicle-cost', $this->filters()))
            ->assertOk()
            ->viewData('report');

        $this->assertSame(1000.0, $report['rows'][0]['gelir']);
        $this->assertSame(1000.0, $report['rows'][0]['yakit']);
        $this->assertSame(100.0, $report['rows'][0]['piyasa']);
    }

    public function test_owner_summary_aggregates_by_snapshot_owner(): void
    {
        $this->makeHaul($this->vehicleA, $this->owner, '2026-09-05 08:00:00', 2000);
        $this->makeHaul($this->vehicleB, $this->owner, '2026-09-06 08:00:00', 3000);
        $this->makeHaul($this->otherVehicle, $this->otherOwner, '2026-09-05 08:00:00', 1000);

        $this->makeReceipt($this->vehicleA, $this->owner, '2026-09-10', 10, 50);
        $this->makeReceipt($this->otherVehicle, $this->otherOwner, '2026-09-10', 20, 50);

        $this->makeMarket($this->vehicleA, $this->owner, '2026-09-12', credit: 100);
        $this->makeMarket($this->otherVehicle, $this->otherOwner, '2026-09-12', debit: 250);

        $report = $this->actingAs($this->admin)
            ->get(route('admin.reports.vehicle-cost.owners', [
                'period' => 'month',
                'year' => 2026,
                'month' => 9,
            ]))
            ->assertOk()
            ->assertSee('Plaka Sahibi Özet', false)
            ->viewData('report');

        $byName = collect($report['rows'])->keyBy('owner_name');

        $this->assertSame(5000.0, $byName['Fahri Doğan']['gelir']);
        $this->assertSame(500.0, $byName['Fahri Doğan']['yakit']);
        $this->assertSame(100.0, $byName['Fahri Doğan']['piyasa']);
        $this->assertSame(4400.0, $byName['Fahri Doğan']['durum']);

        $this->assertSame(1000.0, $byName['Mehmet Demir']['gelir']);
        $this->assertSame(1000.0, $byName['Mehmet Demir']['yakit']);
        $this->assertSame(250.0, $byName['Mehmet Demir']['piyasa']);
        $this->assertSame(-250.0, $byName['Mehmet Demir']['durum']);
    }

    public function test_csv_export_contains_report_rows(): void
    {
        $this->makeHaul($this->vehicleA, $this->owner, '2026-09-05 08:00:00', 2000);
        $this->makeReceipt($this->vehicleA, $this->owner, '2026-09-10', 10, 50);
        $this->makeMarket($this->vehicleA, $this->owner, '2026-09-12', credit: 100);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.reports.vehicle-cost.export', $this->filters()));

        $response->assertOk();
        $csv = $response->streamedContent();

        $this->assertStringContainsString('34 BR 2258', $csv);
        $this->assertStringContainsString('Gelir', $csv);
        $this->assertStringContainsString('1 araç', $csv);
    }

    public function test_invalid_owner_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.reports.vehicle-cost', $this->filters([
                'owner' => 'not-a-uuid',
            ])))
            ->assertRedirect(route('admin.reports.vehicle-cost'))
            ->assertSessionHasErrors('owner');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function filters(array $overrides = []): array
    {
        return array_merge([
            'owner' => $this->owner->id,
            'period' => 'month',
            'year' => 2026,
            'month' => 9,
        ], $overrides);
    }

    private function makeHaul(Vehicle $vehicle, Party $owner, string $hauledAt, float $price): Haul
    {
        return Haul::factory()->create([
            'vehicle_id' => $vehicle->id,
            'party_id' => $owner->id,
            'route_id' => $this->route->id,
            'site_id' => $this->route->site_id,
            'dump_site_id' => $this->route->dump_site_id,
            'dump_party_id' => $this->route->dumpSite->party_id,
            'price' => $price,
            'hauled_at' => $hauledAt,
        ]);
    }

    private function makeReceipt(
        Vehicle $vehicle,
        Party $owner,
        string $issuedOn,
        float $quantity,
        float $unitPrice,
    ): FuelReceipt {
        return FuelReceipt::factory()->create([
            'vehicle_id' => $vehicle->id,
            'party_id' => $owner->id,
            'fuel_station_id' => $this->station->id,
            'fuel_party_id' => $this->station->party_id,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'issued_on' => $issuedOn,
        ]);
    }

    private function makeMarket(
        Vehicle $vehicle,
        Party $owner,
        string $occurredOn,
        float $credit = 0,
        float $debit = 0,
    ): CompanyTransaction {
        return CompanyTransaction::query()->create([
            'party_id' => $this->marketFirm->id,
            'role' => PartyRole::Market,
            'occurred_on' => $occurredOn,
            'debit' => $debit,
            'credit' => $credit,
            'payment_method' => PaymentMethod::Cash,
            'vehicle_id' => $vehicle->id,
            'owner_party_id' => $owner->id,
            'description' => 'Piyasa',
        ]);
    }
}
