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
use App\Services\Ledger\Ledger;
use App\Services\Ledger\LedgerLine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class VehicleMonthlyReportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Party $owner;

    private Vehicle $vehicle;

    private Vehicle $otherVehicle;

    private HaulRoute $routeA;

    private HaulRoute $routeB;

    private FuelStation $station;

    private DumpSite $dumpSite;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
        $this->owner = Party::factory()->create();
        $this->dumpSite = DumpSite::factory()->create(['name' => '212']);

        $siteA = Site::factory()->create(['name' => 'BAKIRKÖY GÜL İNŞ']);
        $siteB = Site::factory()->create(['name' => 'TOPKAPI ALK']);

        $this->routeA = HaulRoute::factory()->create([
            'site_id' => $siteA->id,
            'dump_site_id' => $this->dumpSite->id,
        ]);
        $this->routeB = HaulRoute::factory()->create([
            'site_id' => $siteB->id,
            'dump_site_id' => $this->dumpSite->id,
        ]);

        $this->station = FuelStation::factory()->create([
            'name' => 'ALTUR',
            'unit_price' => 50.00,
        ]);

        $this->vehicle = Vehicle::factory()->ownedBy($this->owner, '2026-01-01')->create([
            'plate' => '34 ABR 69',
        ]);
        $this->otherVehicle = Vehicle::factory()->ownedBy($this->owner, '2026-01-01')->create([
            'plate' => '34 XYZ 01',
        ]);
    }

    public function test_guest_is_redirected_from_the_vehicle_monthly_report(): void
    {
        $this->get(route('admin.reports.vehicle-monthly'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_report_page_shows_filters_with_previous_month_defaults(): void
    {
        $previous = now()->subMonthNoOverflow();

        $this->actingAs($this->admin)
            ->get(route('admin.reports.vehicle-monthly'))
            ->assertOk()
            ->assertSee('Plaka aylık döküm', false)
            ->assertSee('Raporu Göster', false)
            ->assertSee('Raporu görmek için seçim yapın.', false)
            ->assertSee((string) $previous->year, false);
    }

    public function test_report_builds_haul_fuel_payment_and_receivable_rows(): void
    {
        $this->makeHaul($this->vehicle, $this->routeA, '2026-09-05 08:00:00', 2000);
        $this->makeHaul($this->vehicle, $this->routeA, '2026-09-06 08:00:00', 2200);
        $this->makeHaul($this->vehicle, $this->routeB, '2026-09-07 08:00:00', 1800);
        $this->makeHaul($this->otherVehicle, $this->routeA, '2026-09-05 08:00:00', 9999);

        $this->makeReceipt($this->vehicle, '2026-09-10', 100, 50);
        $this->makeReceipt($this->vehicle, '2026-09-12', 50, 52);

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
            'description' => 'Hakediş',
        ]);

        $this->makePaidFine($this->vehicle, '2026-09-18', 250, 'Ceza ödemesi');

        $response = $this->actingAs($this->admin)
            ->get(route('admin.reports.vehicle-monthly', [
                'plate' => '34 ABR 69',
                'year' => 2026,
                'month' => 9,
            ]));

        $response->assertOk()
            ->assertSee('Aylık Plaka Raporu', false)
            ->assertSee('34 ABR 69', false)
            ->assertSee('BAKIRKÖY GÜL İNŞ - 212', false)
            ->assertSee('TOPKAPI ALK - 212', false)
            ->assertSee('ALTUR', false)
            ->assertSee('Avans', false)
            ->assertSee('Hakediş', false)
            ->assertSee('Ceza ödemesi', false)
            ->assertSee('Geçmiş Dönemler', false);

        $report = $response->viewData('report');

        $this->assertFalse(
            collect($report['rows'])->contains(fn (array $row): bool => str_contains($row['description'], '34 XYZ 01')),
        );

        $this->assertSame(3, $report['trips']);
        $this->assertSame(150.0, $report['fuel_liters']);
        $this->assertSame(2, $report['fuel_receipts']);
        $this->assertSame(4, $report['fuel_haul_tickets']);

        $hauls = collect($report['rows'])->where('type', 'haul')->values();
        $this->assertCount(2, $hauls);
        $this->assertSame(2.0, $hauls[0]['quantity']);
        $this->assertSame(4200.0, $hauls[0]['credit']);
        $this->assertEqualsWithDelta(2100.0, $hauls[0]['unit_price'], 0.0001);
        $this->assertSame(1.0, $hauls[1]['quantity']);
        $this->assertSame(1800.0, $hauls[1]['credit']);

        $fuel = collect($report['rows'])->firstWhere('type', 'fuel');
        $this->assertSame(150.0, $fuel['quantity']);
        $this->assertSame('lt', $fuel['quantity_unit']);
        $this->assertSame(7600.0, $fuel['debit']);
        $this->assertEqualsWithDelta(50.6667, $fuel['unit_price'], 0.001);

        $payment = collect($report['rows'])->firstWhere('type', 'payment');
        $this->assertSame('2026-09-15', $payment['date']);
        $this->assertSame(1500.0, $payment['debit']);

        $receivable = collect($report['rows'])->firstWhere('type', 'receivable');
        $this->assertSame(800.0, $receivable['credit']);

        $fine = collect($report['rows'])->firstWhere('type', 'fine');
        $this->assertSame(250.0, $fine['debit']);

        // 4200 + 1800 + 800 credit; 7600 + 1500 + 250 debit
        $this->assertSame(6800.0, $report['total_credit']);
        $this->assertSame(9350.0, $report['total_debit']);
        $this->assertSame(-2550.0, $report['balance']);
    }

    public function test_carryover_from_prior_ledger_balance_is_included(): void
    {
        $haul = $this->makeHaul($this->vehicle, $this->routeA, '2026-08-10 08:00:00', 5000);
        app(Ledger::class)->sync($haul, [
            LedgerLine::credit($this->owner->id, Carbon::parse('2026-08-10'), 5000, 'Önceki taşıma', $this->vehicle->id),
        ]);

        $report = $this->actingAs($this->admin)
            ->get(route('admin.reports.vehicle-monthly', [
                'plate' => '34abr69',
                'year' => 2026,
                'month' => 9,
            ]))
            ->assertOk()
            ->viewData('report');

        $carryover = collect($report['rows'])->firstWhere('type', 'carryover');

        $this->assertNull($carryover['debit']);
        $this->assertSame(5000.0, $carryover['credit']);
        $this->assertSame(5000.0, $report['total_credit']);
        $this->assertSame(0.0, $report['total_debit']);
        $this->assertSame(5000.0, $report['balance']);
    }

    public function test_entries_outside_the_month_are_excluded(): void
    {
        $this->makeHaul($this->vehicle, $this->routeA, '2026-08-31 23:00:00', 1000);
        $this->makeHaul($this->vehicle, $this->routeA, '2026-10-01 00:00:00', 1000);
        $this->makeHaul($this->vehicle, $this->routeA, '2026-09-15 10:00:00', 1500);

        $this->makeReceipt($this->vehicle, '2026-08-31', 40, 50);
        $this->makeReceipt($this->vehicle, '2026-10-01', 40, 50);
        $this->makeReceipt($this->vehicle, '2026-09-15', 80, 50);

        $report = $this->actingAs($this->admin)
            ->get(route('admin.reports.vehicle-monthly', [
                'plate' => '34 ABR 69',
                'year' => 2026,
                'month' => 9,
            ]))
            ->assertOk()
            ->viewData('report');

        $this->assertSame(1, $report['trips']);
        $this->assertSame(80.0, $report['fuel_liters']);
        $this->assertSame(1, $report['fuel_receipts']);
        $this->assertSame(1500.0, $report['total_credit']);
        $this->assertSame(4000.0, $report['total_debit']);
    }

    public function test_date_range_filters_entries_inclusively(): void
    {
        $this->makeHaul($this->vehicle, $this->routeA, '2026-09-01 00:00:00', 1000);
        $this->makeHaul($this->vehicle, $this->routeA, '2026-09-10 12:00:00', 1200);
        $this->makeHaul($this->vehicle, $this->routeA, '2026-09-15 23:30:00', 1300);
        $this->makeHaul($this->vehicle, $this->routeA, '2026-09-16 00:00:00', 9999);

        $this->makeReceipt($this->vehicle, '2026-09-01', 40, 50);
        $this->makeReceipt($this->vehicle, '2026-09-15', 60, 50);
        $this->makeReceipt($this->vehicle, '2026-09-16', 100, 50);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.reports.vehicle-monthly', [
                'plate' => '34 ABR 69',
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

    public function test_date_range_defaults_to_previous_month_bounds(): void
    {
        $previous = now()->subMonthNoOverflow();

        $filters = $this->actingAs($this->admin)
            ->get(route('admin.reports.vehicle-monthly', [
                'period' => 'range',
            ]))
            ->assertOk()
            ->viewData('filters');

        $this->assertSame('range', $filters['period']);
        $this->assertSame($previous->copy()->startOfMonth()->toDateString(), $filters['from']);
        $this->assertSame($previous->copy()->endOfMonth()->toDateString(), $filters['to']);
    }

    public function test_soft_deleted_rows_are_excluded(): void
    {
        $haul = $this->makeHaul($this->vehicle, $this->routeA, '2026-09-05 08:00:00', 2000);
        $haul->delete();
        $this->makeHaul($this->vehicle, $this->routeA, '2026-09-06 08:00:00', 1800);

        $receipt = $this->makeReceipt($this->vehicle, '2026-09-10', 100, 50);
        $receipt->delete();
        $this->makeReceipt($this->vehicle, '2026-09-11', 60, 50);

        $report = $this->actingAs($this->admin)
            ->get(route('admin.reports.vehicle-monthly', [
                'plate' => '34 ABR 69',
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
        $this->makeReceipt($this->vehicle, '2026-09-10', 100, 50);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.reports.vehicle-monthly.export', [
                'plate' => '34 ABR 69',
                'year' => 2026,
                'month' => 9,
            ]));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));

        $csv = $response->streamedContent();

        $this->assertStringContainsString('Açıklama', $csv);
        $this->assertStringContainsString('BAKIRKÖY GÜL İNŞ - 212', $csv);
        $this->assertStringContainsString('ALTUR', $csv);
        $this->assertStringContainsString('Geçmiş Dönemler', $csv);
        $this->assertStringContainsString('Bakiye', $csv);
    }

    public function test_export_requires_a_plate(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.reports.vehicle-monthly.export', [
                'year' => 2026,
                'month' => 9,
            ]))
            ->assertRedirect(route('admin.reports.vehicle-monthly'))
            ->assertSessionHasErrors('plate');
    }

    public function test_unknown_plate_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.reports.vehicle-monthly', [
                'plate' => '99 UNKNOWN',
                'year' => 2026,
                'month' => 9,
            ]))
            ->assertRedirect(route('admin.reports.vehicle-monthly'))
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
    ): FuelReceipt {
        return FuelReceipt::factory()->create([
            'vehicle_id' => $vehicle->id,
            'party_id' => $this->owner->id,
            'fuel_station_id' => $this->station->id,
            'fuel_party_id' => $this->station->party_id,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'issued_on' => $issuedOn,
            'haul_ticket_count' => $haulTicketCount,
        ]);
    }

    private function makePaidFine(Vehicle $vehicle, string $paidOn, float $amount, string $description): void
    {
        DB::table('vehicle_fines')->insert([
            'id' => (string) Str::uuid(),
            'vehicle_id' => $vehicle->id,
            'party_id' => $this->owner->id,
            'amount' => $amount,
            'occurred_on' => $paidOn,
            'status' => 'paid',
            'paid_on' => $paidOn,
            'description' => $description,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
