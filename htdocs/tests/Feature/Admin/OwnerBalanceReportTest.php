<?php

namespace Tests\Feature\Admin;

use App\Models\DumpSite;
use App\Models\FuelReceipt;
use App\Models\FuelStation;
use App\Models\Haul;
use App\Models\HaulRoute;
use App\Models\Party;
use App\Models\PartyPayment;
use App\Models\Site;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehiclePayment;
use App\Models\VehicleReceivable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class OwnerBalanceReportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Party $owner;

    private Party $otherOwner;

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
        $this->owner = Party::factory()->create(['name' => 'Ahmet Yılmaz']);
        $this->otherOwner = Party::factory()->create(['name' => 'Mehmet Demir']);
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
        $this->otherVehicle = Vehicle::factory()->ownedBy($this->otherOwner, '2026-01-01')->create([
            'plate' => '34 XYZ 01',
        ]);
    }

    public function test_guest_is_redirected_from_the_owner_balance_report(): void
    {
        $this->get(route('admin.reports.owner-balance'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_report_page_shows_filters_without_running(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.reports.owner-balance'))
            ->assertOk()
            ->assertSee('Plaka sahibi bakiye', false)
            ->assertSee('Şantiye, yakıt yeri ve dönemi seçip Raporu Göster’e basın.', false)
            ->assertViewHas('ready', false);
    }

    public function test_report_requires_site_and_fuel_station(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.reports.owner-balance', [
                'period' => 'month',
                'year' => 2026,
                'month' => 9,
            ]))
            ->assertRedirect(route('admin.reports.owner-balance'))
            ->assertSessionHasErrors(['site', 'fuel_station']);
    }

    public function test_report_builds_totals_per_owner_including_owner_payment(): void
    {
        $this->makeHaul($this->vehicle, $this->owner, $this->routeA, '2026-09-05 08:00:00', 2000);
        $this->makeHaul($this->vehicle, $this->owner, $this->routeA, '2026-09-06 08:00:00', 2200);
        $this->makeHaul($this->otherVehicle, $this->otherOwner, $this->routeA, '2026-09-05 08:00:00', 1000);

        $this->makeReceipt($this->vehicle, $this->owner, $this->stationA, '2026-09-10', 100, 50);
        $this->makeReceipt($this->otherVehicle, $this->otherOwner, $this->stationA, '2026-09-11', 40, 50);

        VehiclePayment::query()->create([
            'vehicle_id' => $this->vehicle->id,
            'party_id' => $this->owner->id,
            'amount' => 1500,
            'occurred_on' => '2026-09-15',
        ]);

        VehicleReceivable::query()->create([
            'vehicle_id' => $this->vehicle->id,
            'party_id' => $this->owner->id,
            'amount' => 800,
            'occurred_on' => '2026-09-20',
        ]);

        $this->makePaidFine($this->vehicle, $this->owner, '2026-09-18', 250);

        PartyPayment::query()->create([
            'party_id' => $this->owner->id,
            'amount' => 400,
            'occurred_on' => '2026-09-22',
            'description' => 'Sahip ödemesi',
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.reports.owner-balance', $this->filters()));

        $response->assertOk()
            ->assertSee('Plaka Sahibi Bakiye Raporu', false)
            ->assertSee('Ahmet Yılmaz', false)
            ->assertSee('Mehmet Demir', false)
            ->assertSee('Filtreli net', false);

        $report = $response->viewData('report');
        $this->assertCount(2, $report['rows']);

        $ahmet = $report['rows'][0];
        $this->assertSame('Ahmet Yılmaz', $ahmet['name']);
        $this->assertSame(4200.0, $ahmet['credit']);
        $this->assertSame(800.0, $ahmet['cash']);
        $this->assertSame(7150.0, $ahmet['debit']);
        $this->assertSame(2150.0, $ahmet['balance']);

        $mehmet = $report['rows'][1];
        $this->assertSame(1000.0, $mehmet['credit']);
        $this->assertSame(2000.0, $mehmet['debit']);
    }

    public function test_snapshot_party_keeps_activity_with_prior_owner(): void
    {
        $this->makeHaul($this->vehicle, $this->owner, $this->routeA, '2026-09-05 08:00:00', 3000);
        $this->makeReceipt($this->vehicle, $this->owner, $this->stationA, '2026-09-06', 20, 50);
        $this->makeHaul($this->vehicle, $this->otherOwner, $this->routeA, '2026-09-10 08:00:00', 1500);
        $this->makeReceipt($this->vehicle, $this->otherOwner, $this->stationA, '2026-09-11', 10, 50);

        $report = $this->actingAs($this->admin)
            ->get(route('admin.reports.owner-balance', $this->filters()))
            ->assertOk()
            ->viewData('report');

        $byName = collect($report['rows'])->keyBy('name');
        $this->assertSame(3000.0, $byName['Ahmet Yılmaz']['credit']);
        $this->assertSame(1500.0, $byName['Mehmet Demir']['credit']);
    }

    public function test_hides_owners_missing_site_haul_or_station_fuel(): void
    {
        $this->makeHaul($this->vehicle, $this->owner, $this->routeA, '2026-09-05 08:00:00', 1500);
        $this->makeReceipt($this->vehicle, $this->owner, $this->stationA, '2026-09-10', 40, 50);

        $this->makeHaul($this->otherVehicle, $this->otherOwner, $this->routeB, '2026-09-05 08:00:00', 3000);
        $this->makeReceipt($this->otherVehicle, $this->otherOwner, $this->stationA, '2026-09-11', 40, 50);

        $report = $this->actingAs($this->admin)
            ->get(route('admin.reports.owner-balance', $this->filters()))
            ->assertOk()
            ->viewData('report');

        $this->assertCount(1, $report['rows']);
        $this->assertSame('Ahmet Yılmaz', $report['rows'][0]['name']);
    }

    public function test_date_range_includes_end_day(): void
    {
        $this->makeHaul($this->vehicle, $this->owner, $this->routeA, '2026-09-01 00:00:00', 1000);
        $this->makeHaul($this->vehicle, $this->owner, $this->routeA, '2026-09-15 23:30:00', 1300);
        $this->makeHaul($this->vehicle, $this->owner, $this->routeA, '2026-09-16 00:00:00', 9999);
        $this->makeReceipt($this->vehicle, $this->owner, $this->stationA, '2026-09-01', 40, 50);

        $report = $this->actingAs($this->admin)
            ->get(route('admin.reports.owner-balance', $this->filters([
                'period' => 'range',
                'from' => '2026-09-01',
                'to' => '2026-09-15',
            ])))
            ->assertOk()
            ->viewData('report');

        $this->assertSame(2300.0, $report['rows'][0]['credit']);
    }

    public function test_csv_export_contains_owner_rows(): void
    {
        $this->makeHaul($this->vehicle, $this->owner, $this->routeA, '2026-09-05 08:00:00', 2000);
        $this->makeReceipt($this->vehicle, $this->owner, $this->stationA, '2026-09-10', 40, 50);

        $csv = $this->actingAs($this->admin)
            ->get(route('admin.reports.owner-balance.export', $this->filters()))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('Ahmet Yılmaz', $csv);
        $this->assertStringContainsString('Filtreli net', $csv);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
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

    private function makeHaul(Vehicle $vehicle, Party $owner, HaulRoute $route, string $hauledAt, float $price): Haul
    {
        return Haul::factory()->create([
            'vehicle_id' => $vehicle->id,
            'party_id' => $owner->id,
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
        Party $owner,
        FuelStation $station,
        string $issuedOn,
        float $quantity,
        float $unitPrice,
        ?string $siteId = null,
    ): FuelReceipt {
        return FuelReceipt::factory()->create([
            'vehicle_id' => $vehicle->id,
            'party_id' => $owner->id,
            'fuel_station_id' => $station->id,
            'fuel_party_id' => $station->party_id,
            'site_id' => $siteId,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'issued_on' => $issuedOn,
        ]);
    }

    private function makePaidFine(Vehicle $vehicle, Party $owner, string $paidOn, float $amount): void
    {
        DB::table('vehicle_fines')->insert([
            'id' => (string) Str::uuid(),
            'vehicle_id' => $vehicle->id,
            'party_id' => $owner->id,
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
