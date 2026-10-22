<?php

namespace Tests\Feature\Admin;

use App\Models\FuelReceipt;
use App\Models\FuelStation;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FuelStationMonthlyReportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private FuelStation $station;

    private VehicleClass $tir;

    private Vehicle $vehicleA;

    private Vehicle $vehicleB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->yonetici()->create();
        $this->station = FuelStation::factory()->create(['name' => 'SHELL']);
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
        $this->get(route('admin.reports.fuel-stations-monthly'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_day_matrix_sums_liters(): void
    {
        $this->makeReceipt($this->vehicleA, '2026-09-01', 40);
        $this->makeReceipt($this->vehicleA, '2026-09-01', 20);
        $this->makeReceipt($this->vehicleA, '2026-09-05', 50);
        $this->makeReceipt($this->vehicleB, '2026-09-05', 30);
        $this->makeReceipt($this->vehicleA, '2026-09-25', 10);

        $report = $this->actingAs($this->admin)
            ->get(route('admin.reports.fuel-stations-monthly', [
                'fuel_station' => $this->station->id,
                'year' => 2026,
                'month' => 9,
                'day_from' => 1,
                'day_to' => 10,
            ]))
            ->assertOk()
            ->assertSee('SHELL', false)
            ->viewData('report');

        $byPlate = collect($report['rows'])->keyBy('plate');
        $this->assertSame(60.0, $byPlate['34 AAA 01']['liters'][1]);
        $this->assertSame(50.0, $byPlate['34 AAA 01']['liters'][5]);
        $this->assertArrayNotHasKey(25, $byPlate['34 AAA 01']['liters']);
        $this->assertSame(110.0, $byPlate['34 AAA 01']['total']);
        $this->assertSame(30.0, $byPlate['34 BBB 01']['liters'][5]);
        $this->assertSame(140.0, $report['grand_total']);
    }

    public function test_csv_export(): void
    {
        $this->makeReceipt($this->vehicleA, '2026-09-02', 12.5);

        $csv = $this->actingAs($this->admin)
            ->get(route('admin.reports.fuel-stations-monthly.export', [
                'fuel_station' => $this->station->id,
                'year' => 2026,
                'month' => 9,
            ]))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('Plaka', $csv);
        $this->assertStringContainsString('12,50', $csv);
    }

    private function makeReceipt(Vehicle $vehicle, string $issuedOn, float $quantity): FuelReceipt
    {
        return FuelReceipt::factory()->create([
            'vehicle_id' => $vehicle->id,
            'party_id' => $vehicle->currentOwnership->party_id,
            'fuel_station_id' => $this->station->id,
            'fuel_party_id' => $this->station->party_id,
            'quantity' => $quantity,
            'unit_price' => 40,
            'issued_on' => $issuedOn,
        ]);
    }
}
