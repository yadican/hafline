<?php

namespace Tests\Feature\Admin;

use App\Models\DumpSite;
use App\Models\FuelReceipt;
use App\Models\FuelStation;
use App\Models\Haul;
use App\Models\HaulRoute;
use App\Models\Invoice;
use App\Models\Site;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehiclePeriodicReportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Vehicle $vehicle;

    private HaulRoute $route;

    private FuelStation $station;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->yonetici()->create();
        $this->vehicle = Vehicle::factory()->create(['plate' => '34 AAA 01']);
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
        $this->get(route('admin.reports.vehicle-periodic'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_report_shows_haul_and_fuel_quantity_rows(): void
    {
        Haul::factory()->create([
            'vehicle_id' => $this->vehicle->id,
            'party_id' => $this->vehicle->currentOwnership->party_id,
            'route_id' => $this->route->id,
            'site_id' => $this->route->site_id,
            'dump_site_id' => $this->route->dump_site_id,
            'dump_party_id' => $this->route->dumpSite->party_id,
            'tonnage' => 12,
            'hauled_at' => '2026-09-05 10:00:00',
        ]);
        Haul::factory()->create([
            'vehicle_id' => $this->vehicle->id,
            'party_id' => $this->vehicle->currentOwnership->party_id,
            'route_id' => $this->route->id,
            'site_id' => $this->route->site_id,
            'dump_site_id' => $this->route->dump_site_id,
            'dump_party_id' => $this->route->dumpSite->party_id,
            'tonnage' => 8,
            'hauled_at' => '2026-09-06 10:00:00',
        ]);
        FuelReceipt::factory()->create([
            'vehicle_id' => $this->vehicle->id,
            'party_id' => $this->vehicle->currentOwnership->party_id,
            'fuel_station_id' => $this->station->id,
            'fuel_party_id' => $this->station->party_id,
            'quantity' => 55.5,
            'issued_on' => '2026-09-07',
        ]);

        $report = $this->actingAs($this->admin)
            ->get(route('admin.reports.vehicle-periodic', [
                'plate' => '34 AAA 01',
                'period' => 'month',
                'year' => 2026,
                'month' => 9,
            ]))
            ->assertOk()
            ->assertSee('TOPKAPI - 212', false)
            ->assertSee('SHELL', false)
            ->viewData('report');

        $this->assertSame(2, $report['total_trips']);
        $this->assertSame(20.0, $report['total_tonnage']);
        $this->assertSame(55.5, $report['total_liters']);
        $this->assertSame(0.0, $report['total_material']);
        $this->assertCount(2, $report['rows']);
    }

    public function test_plate_sale_appears_as_material_row(): void
    {
        Invoice::factory()->sale()->create([
            'party_id' => $this->vehicle->currentOwnership->party_id,
            'vehicle_id' => $this->vehicle->id,
            'invoice_no' => 'VP-50',
            'issued_on' => '2026-09-10',
            'description' => null,
            'subtotal' => 850,
            'vat_amount' => 0,
            'total' => 850,
            'is_draft' => false,
        ]);

        Invoice::factory()->sale()->draft()->create([
            'party_id' => $this->vehicle->currentOwnership->party_id,
            'vehicle_id' => $this->vehicle->id,
            'issued_on' => '2026-09-11',
            'subtotal' => 100,
            'vat_amount' => 0,
            'total' => 100,
        ]);

        $report = $this->actingAs($this->admin)
            ->get(route('admin.reports.vehicle-periodic', [
                'plate' => '34 AAA 01',
                'period' => 'month',
                'year' => 2026,
                'month' => 9,
            ]))
            ->assertOk()
            ->assertSee('Malzeme · VP-50', false)
            ->viewData('report');

        $material = collect($report['rows'])->firstWhere('type', 'material');
        $this->assertSame(850.0, $material['amount']);
        $this->assertSame(850.0, $report['total_material']);
        $this->assertCount(1, $report['rows']);
    }

    public function test_csv_export(): void
    {
        Haul::factory()->create([
            'vehicle_id' => $this->vehicle->id,
            'party_id' => $this->vehicle->currentOwnership->party_id,
            'route_id' => $this->route->id,
            'site_id' => $this->route->site_id,
            'dump_site_id' => $this->route->dump_site_id,
            'dump_party_id' => $this->route->dumpSite->party_id,
            'tonnage' => 10,
            'hauled_at' => '2026-09-05 10:00:00',
        ]);

        $csv = $this->actingAs($this->admin)
            ->get(route('admin.reports.vehicle-periodic.export', [
                'plate' => '34 AAA 01',
                'period' => 'month',
                'year' => 2026,
                'month' => 9,
            ]))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('Döküm Sayısı', $csv);
        $this->assertStringContainsString('Toplam', $csv);
    }
}
