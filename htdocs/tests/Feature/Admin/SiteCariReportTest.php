<?php

namespace Tests\Feature\Admin;

use App\Enums\PaymentMethod;
use App\Models\DumpSite;
use App\Models\FuelReceipt;
use App\Models\FuelStation;
use App\Models\Haul;
use App\Models\HaulRoute;
use App\Models\Party;
use App\Models\Site;
use App\Models\SiteTransaction;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteCariReportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Party $owner;

    private Vehicle $vehicle;

    private Site $siteA;

    private Site $siteB;

    private HaulRoute $routeA;

    private HaulRoute $routeB;

    private FuelStation $station;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->yonetici()->create();
        $this->owner = Party::factory()->create(['name' => 'Ahmet']);
        $dump = DumpSite::factory()->create(['name' => '212']);
        $this->siteA = Site::factory()->create(['name' => 'BAKIRKÖY']);
        $this->siteB = Site::factory()->create(['name' => 'TOPKAPI']);
        $this->routeA = HaulRoute::factory()->create([
            'site_id' => $this->siteA->id,
            'dump_site_id' => $dump->id,
        ]);
        $this->routeB = HaulRoute::factory()->create([
            'site_id' => $this->siteB->id,
            'dump_site_id' => $dump->id,
        ]);
        $this->station = FuelStation::factory()->create(['name' => 'ALTUR', 'unit_price' => 50]);
        $this->vehicle = Vehicle::factory()->ownedBy($this->owner, '2026-01-01')->create([
            'plate' => '34 ABR 69',
        ]);
    }

    public function test_guest_is_redirected(): void
    {
        $this->get(route('admin.reports.site-cari'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_page_shows_filters_without_running(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.reports.site-cari'))
            ->assertOk()
            ->assertSee('Şantiye cari', false)
            ->assertViewHas('ready', false);
    }

    public function test_report_builds_totals_and_status_per_site(): void
    {
        Haul::factory()->create([
            'vehicle_id' => $this->vehicle->id,
            'party_id' => $this->owner->id,
            'route_id' => $this->routeA->id,
            'site_id' => $this->siteA->id,
            'dump_site_id' => $this->routeA->dump_site_id,
            'dump_party_id' => $this->routeA->dumpSite->party_id,
            'price' => 1000,
            'dump_price' => 200,
            'hauled_at' => '2026-09-05 08:00:00',
        ]);
        Haul::factory()->create([
            'vehicle_id' => $this->vehicle->id,
            'party_id' => $this->owner->id,
            'route_id' => $this->routeA->id,
            'site_id' => $this->siteA->id,
            'dump_site_id' => $this->routeA->dump_site_id,
            'dump_party_id' => $this->routeA->dumpSite->party_id,
            'price' => 500,
            'dump_price' => 100,
            'hauled_at' => '2026-09-06 08:00:00',
        ]);
        Haul::factory()->create([
            'vehicle_id' => $this->vehicle->id,
            'party_id' => $this->owner->id,
            'route_id' => $this->routeB->id,
            'site_id' => $this->siteB->id,
            'dump_site_id' => $this->routeB->dump_site_id,
            'dump_party_id' => $this->routeB->dumpSite->party_id,
            'price' => 800,
            'dump_price' => 50,
            'hauled_at' => '2026-09-07 08:00:00',
        ]);

        FuelReceipt::factory()->create([
            'vehicle_id' => $this->vehicle->id,
            'party_id' => $this->owner->id,
            'fuel_station_id' => $this->station->id,
            'fuel_party_id' => $this->station->party_id,
            'site_id' => $this->siteA->id,
            'quantity' => 40,
            'unit_price' => 50,
            'issued_on' => '2026-09-10',
        ]);

        SiteTransaction::query()->create([
            'site_id' => $this->siteA->id,
            'payment_method' => PaymentMethod::Cash,
            'debit' => 150,
            'credit' => 0,
            'occurred_on' => '2026-09-12',
            'description' => 'Diğer',
        ]);
        SiteTransaction::query()->create([
            'site_id' => $this->siteA->id,
            'payment_method' => PaymentMethod::Cash,
            'debit' => 0,
            'credit' => 5000,
            'occurred_on' => '2026-09-15',
            'description' => 'Hakediş',
        ]);

        // Outside period — ignored
        Haul::factory()->create([
            'vehicle_id' => $this->vehicle->id,
            'party_id' => $this->owner->id,
            'route_id' => $this->routeA->id,
            'site_id' => $this->siteA->id,
            'dump_site_id' => $this->routeA->dump_site_id,
            'dump_party_id' => $this->routeA->dumpSite->party_id,
            'price' => 9999,
            'dump_price' => 999,
            'hauled_at' => '2026-10-01 00:00:00',
        ]);

        $report = $this->actingAs($this->admin)
            ->get(route('admin.reports.site-cari', $this->filters()))
            ->assertOk()
            ->assertSee('Şantiye Cari Raporu', false)
            ->viewData('report');

        $byName = collect($report['rows'])->keyBy('name');

        $this->assertSame(300.0, $byName['BAKIRKÖY']['dump']);
        $this->assertSame(1500.0, $byName['BAKIRKÖY']['haul']);
        $this->assertSame(2, $byName['BAKIRKÖY']['haul_count']);
        $this->assertSame(2000.0, $byName['BAKIRKÖY']['fuel']);
        $this->assertSame(1, $byName['BAKIRKÖY']['fuel_count']);
        $this->assertSame(150.0, $byName['BAKIRKÖY']['other']);
        $this->assertSame(5000.0, $byName['BAKIRKÖY']['earned']);
        // Durum = 5000 − (300 + 1500 + 2000 + 150) = 1050
        $this->assertSame(1050.0, $byName['BAKIRKÖY']['status']);

        $this->assertSame(50.0, $byName['TOPKAPI']['dump']);
        $this->assertSame(800.0, $byName['TOPKAPI']['haul']);
        $this->assertSame(1, $byName['TOPKAPI']['haul_count']);
        $this->assertSame(-850.0, $byName['TOPKAPI']['status']);
    }

    public function test_soft_deleted_site_transactions_excluded(): void
    {
        $tx = SiteTransaction::query()->create([
            'site_id' => $this->siteA->id,
            'debit' => 0,
            'credit' => 1000,
            'occurred_on' => '2026-09-10',
        ]);
        $tx->delete();

        SiteTransaction::query()->create([
            'site_id' => $this->siteA->id,
            'debit' => 0,
            'credit' => 400,
            'occurred_on' => '2026-09-11',
        ]);

        $report = $this->actingAs($this->admin)
            ->get(route('admin.reports.site-cari', $this->filters()))
            ->viewData('report');

        $this->assertSame(400.0, $report['rows'][0]['earned']);
    }

    public function test_csv_export(): void
    {
        SiteTransaction::query()->create([
            'site_id' => $this->siteA->id,
            'debit' => 0,
            'credit' => 250,
            'occurred_on' => '2026-09-10',
        ]);

        $csv = $this->actingAs($this->admin)
            ->get(route('admin.reports.site-cari.export', $this->filters()))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('Şantiye;', $csv);
        $this->assertStringContainsString('BAKIRKÖY', $csv);
        $this->assertStringContainsString('250,00', $csv);
    }

    /**
     * @return array{period: string, year: int, month: int}
     */
    private function filters(): array
    {
        return [
            'period' => 'month',
            'year' => 2026,
            'month' => 9,
        ];
    }
}
