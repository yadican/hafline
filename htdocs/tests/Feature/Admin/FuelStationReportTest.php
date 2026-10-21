<?php

namespace Tests\Feature\Admin;

use App\Models\FuelReceipt;
use App\Models\FuelStation;
use App\Models\Party;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FuelStationReportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private FuelStation $station;

    private VehicleClass $makine;

    private VehicleClass $tir;

    private Vehicle $vehicleA;

    private Vehicle $vehicleB;

    private Party $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
        $this->station = FuelStation::factory()->create([
            'name' => 'ALTUR',
            'unit_price' => 50.00,
        ]);

        $this->makine = VehicleClass::factory()->create(['name' => 'MAKİNE']);
        $this->tir = VehicleClass::factory()->create(['name' => 'TIR']);
        $this->owner = Party::factory()->create();

        $this->vehicleA = Vehicle::factory()->ownedBy($this->owner, '2026-01-01')->create([
            'plate' => 'CAT 955',
            'vehicle_class_id' => $this->makine->id,
        ]);
        $this->vehicleB = Vehicle::factory()->ownedBy($this->owner, '2026-01-01')->create([
            'plate' => '34 L 02',
            'vehicle_class_id' => $this->tir->id,
        ]);
    }

    public function test_guest_is_redirected_from_the_fuel_station_report(): void
    {
        $this->get(route('admin.reports.fuel-stations'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_report_page_shows_filters_with_previous_month_defaults(): void
    {
        $previous = now()->subMonthNoOverflow();

        $this->actingAs($this->admin)
            ->get(route('admin.reports.fuel-stations'))
            ->assertOk()
            ->assertSee('Yakıt yeri raporları', false)
            ->assertSee('Raporu Göster', false)
            ->assertSee('Raporu görmek için seçim yapın.', false)
            ->assertSee((string) $previous->year, false);
    }

    public function test_report_aggregates_liters_and_amounts_by_plate(): void
    {
        $this->seedReceipts();

        $response = $this->actingAs($this->admin)
            ->get(route('admin.reports.fuel-stations', [
                'fuel_station' => $this->station->id,
                'year' => 2026,
                'month' => 9,
            ]));

        $response->assertOk()
            ->assertSee('ALTUR Yakıt Yeri Raporu', false)
            ->assertSee('CAT 955', false)
            ->assertSee('34 L 02', false);

        $report = $response->viewData('report');

        $this->assertSame(300.0, $report['total_liters']);
        $this->assertSame(15100.0, $report['total_amount']);
        $this->assertCount(2, $report['rows']);

        $rowA = collect($report['rows'])->firstWhere('plate', 'CAT 955');
        $rowB = collect($report['rows'])->firstWhere('plate', '34 L 02');

        $this->assertSame(200.0, $rowA['liters']);
        $this->assertSame(10100.0, $rowA['amount']);
        $this->assertEqualsWithDelta(50.5, $rowA['unit_price'], 0.0001);
        $this->assertSame(100.0, $rowB['liters']);
        $this->assertSame(5000.0, $rowB['amount']);
        $this->assertEqualsWithDelta(50.0, $rowB['unit_price'], 0.0001);
    }

    public function test_vehicle_class_filter_excludes_other_classes(): void
    {
        $this->seedReceipts();

        $report = $this->actingAs($this->admin)
            ->get(route('admin.reports.fuel-stations', [
                'fuel_station' => $this->station->id,
                'year' => 2026,
                'month' => 9,
                'class' => $this->makine->id,
            ]))
            ->assertOk()
            ->viewData('report');

        $this->assertCount(1, $report['rows']);
        $this->assertSame('CAT 955', $report['rows'][0]['plate']);
        $this->assertSame(200.0, $report['total_liters']);
        $this->assertSame(10100.0, $report['total_amount']);
    }

    public function test_soft_deleted_receipts_are_excluded(): void
    {
        $receipt = $this->makeReceipt($this->vehicleA, '2026-09-10', 100, 50);
        $receipt->delete();

        $this->makeReceipt($this->vehicleA, '2026-09-11', 80, 50);

        $report = $this->actingAs($this->admin)
            ->get(route('admin.reports.fuel-stations', [
                'fuel_station' => $this->station->id,
                'year' => 2026,
                'month' => 9,
            ]))
            ->assertOk()
            ->viewData('report');

        $this->assertSame(80.0, $report['total_liters']);
        $this->assertSame(4000.0, $report['total_amount']);
    }

    public function test_receipts_outside_the_month_are_excluded(): void
    {
        $this->makeReceipt($this->vehicleA, '2026-08-31', 100, 50);
        $this->makeReceipt($this->vehicleA, '2026-10-01', 100, 50);
        $this->makeReceipt($this->vehicleA, '2026-09-15', 60, 50);

        $report = $this->actingAs($this->admin)
            ->get(route('admin.reports.fuel-stations', [
                'fuel_station' => $this->station->id,
                'year' => 2026,
                'month' => 9,
            ]))
            ->assertOk()
            ->viewData('report');

        $this->assertSame(60.0, $report['total_liters']);
        $this->assertSame(3000.0, $report['total_amount']);
    }

    public function test_date_range_filters_receipts_inclusively(): void
    {
        $this->makeReceipt($this->vehicleA, '2026-09-01', 40, 50);
        $this->makeReceipt($this->vehicleA, '2026-09-10', 50, 50);
        $this->makeReceipt($this->vehicleA, '2026-09-15', 60, 50);
        $this->makeReceipt($this->vehicleA, '2026-09-16', 100, 50);

        $report = $this->actingAs($this->admin)
            ->get(route('admin.reports.fuel-stations', [
                'fuel_station' => $this->station->id,
                'period' => 'range',
                'from' => '2026-09-01',
                'to' => '2026-09-15',
            ]))
            ->assertOk()
            ->viewData('report');

        $this->assertSame('range', $report['period']);
        $this->assertSame(150.0, $report['total_liters']);
        $this->assertSame(7500.0, $report['total_amount']);
    }

    public function test_date_range_defaults_to_previous_month_bounds(): void
    {
        $previous = now()->subMonthNoOverflow();

        $filters = $this->actingAs($this->admin)
            ->get(route('admin.reports.fuel-stations', [
                'period' => 'range',
            ]))
            ->assertOk()
            ->viewData('filters');

        $this->assertSame('range', $filters['period']);
        $this->assertSame($previous->copy()->startOfMonth()->toDateString(), $filters['from']);
        $this->assertSame($previous->copy()->endOfMonth()->toDateString(), $filters['to']);
    }

    public function test_csv_export_contains_report_rows(): void
    {
        $this->seedReceipts();

        $response = $this->actingAs($this->admin)
            ->get(route('admin.reports.fuel-stations.export', [
                'fuel_station' => $this->station->id,
                'year' => 2026,
                'month' => 9,
            ]));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));

        $csv = $response->streamedContent();

        $this->assertStringContainsString('Plaka / Araç', $csv);
        $this->assertStringContainsString('CAT 955', $csv);
        $this->assertStringContainsString('Toplam', $csv);
    }

    public function test_export_requires_a_fuel_station(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.reports.fuel-stations.export', [
                'year' => 2026,
                'month' => 9,
            ]))
            ->assertRedirect(route('admin.reports.fuel-stations'))
            ->assertSessionHasErrors('fuel_station');
    }

    private function seedReceipts(): void
    {
        $this->makeReceipt($this->vehicleA, '2026-09-05', 100, 50);
        $this->makeReceipt($this->vehicleA, '2026-09-07', 100, 51);
        $this->makeReceipt($this->vehicleB, '2026-09-08', 100, 50);
    }

    private function makeReceipt(Vehicle $vehicle, string $issuedOn, float $quantity, float $unitPrice): FuelReceipt
    {
        return FuelReceipt::factory()->create([
            'vehicle_id' => $vehicle->id,
            'party_id' => $this->owner->id,
            'fuel_station_id' => $this->station->id,
            'fuel_party_id' => $this->station->party_id,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'issued_on' => $issuedOn,
        ]);
    }
}
