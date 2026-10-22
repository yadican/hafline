<?php

namespace Tests\Feature\Admin;

use App\Enums\PartyRole;
use App\Enums\PaymentMethod;
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
use App\Services\Ledger\Ledger;
use App\Services\Ledger\LedgerLine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class OwnerMonthlyReportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Party $owner;

    private Party $otherOwner;

    private Vehicle $vehicleA;

    private Vehicle $vehicleB;

    private Vehicle $otherVehicle;

    private HaulRoute $routeA;

    private HaulRoute $routeB;

    private FuelStation $station;

    private DumpSite $dumpSite;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->yonetici()->create();
        $this->owner = Party::factory()->create(['name' => 'Ahmet Yılmaz']);
        $this->otherOwner = Party::factory()->create(['name' => 'Mehmet Demir']);
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

        $this->vehicleA = Vehicle::factory()->ownedBy($this->owner, '2026-01-01')->create([
            'plate' => '34 ABR 69',
        ]);
        $this->vehicleB = Vehicle::factory()->ownedBy($this->owner, '2026-01-01')->create([
            'plate' => '34 XYZ 01',
        ]);
        $this->otherVehicle = Vehicle::factory()->ownedBy($this->otherOwner, '2026-01-01')->create([
            'plate' => '06 ABC 01',
        ]);
    }

    public function test_guest_is_redirected_from_the_owner_monthly_report(): void
    {
        $this->get(route('admin.reports.owner-monthly'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_report_page_shows_filters_with_previous_month_defaults(): void
    {
        $previous = now()->subMonthNoOverflow();

        $this->actingAs($this->admin)
            ->get(route('admin.reports.owner-monthly'))
            ->assertOk()
            ->assertSee('Plaka sahibi aylık döküm', false)
            ->assertSee('Raporu Göster', false)
            ->assertSee('Raporu görmek için seçim yapın.', false)
            ->assertSee((string) $previous->year, false);
    }

    public function test_report_groups_vehicles_and_includes_owner_payments(): void
    {
        $this->makeHaul($this->vehicleA, $this->routeA, '2026-09-05 08:00:00', 2000);
        $this->makeHaul($this->vehicleA, $this->routeA, '2026-09-06 08:00:00', 2200);
        $this->makeHaul($this->vehicleB, $this->routeB, '2026-09-07 08:00:00', 1800);
        $this->makeHaul($this->otherVehicle, $this->routeA, '2026-09-05 08:00:00', 9999);

        $this->makeReceipt($this->vehicleA, '2026-09-10', 100, 50);
        $this->makeReceipt($this->vehicleB, '2026-09-12', 40, 50);

        VehiclePayment::query()->create([
            'vehicle_id' => $this->vehicleA->id,
            'party_id' => $this->owner->id,
            'amount' => 1500,
            'occurred_on' => '2026-09-15',
            'description' => 'Avans',
        ]);

        VehicleReceivable::query()->create([
            'vehicle_id' => $this->vehicleB->id,
            'party_id' => $this->owner->id,
            'amount' => 800,
            'occurred_on' => '2026-09-20',
            'description' => 'Hakediş',
        ]);

        $this->makePaidFine($this->vehicleA, '2026-09-18', 250, 'Ceza ödemesi');
        $this->makeOwnerPayment('2026-09-25', 3000, 'Sahibe nakit');

        $response = $this->actingAs($this->admin)
            ->get(route('admin.reports.owner-monthly', [
                'owner' => $this->owner->id,
                'year' => 2026,
                'month' => 9,
            ]));

        $response->assertOk()
            ->assertSee('Plaka Sahibi Aylık Raporu', false)
            ->assertSee('Ahmet Yılmaz', false)
            ->assertSee('34 ABR 69', false)
            ->assertSee('34 XYZ 01', false)
            ->assertSee('BAKIRKÖY GÜL İNŞ - 212', false)
            ->assertSee('TOPKAPI ALK - 212', false)
            ->assertSee('ALTUR', false)
            ->assertSee('Avans', false)
            ->assertSee('Hakediş', false)
            ->assertSee('Ceza ödemesi', false)
            ->assertSee('Sahibe nakit', false)
            ->assertSee('Plaka sahibi', false)
            ->assertSee('Geçmiş Dönemler', false)
            ->assertDontSee('06 ABC 01', false);

        $report = $response->viewData('report');

        $this->assertCount(2, $report['vehicles']);
        $this->assertSame('34 ABR 69', $report['vehicles'][0]['plate']);
        $this->assertSame('34 XYZ 01', $report['vehicles'][1]['plate']);

        $this->assertSame(3, $report['trips']);
        $this->assertSame(140.0, $report['fuel_liters']);
        $this->assertSame(2, $report['fuel_receipts']);

        $this->assertCount(1, $report['owner_payments']);
        $this->assertSame(3000.0, $report['owner_payments'][0]['debit']);

        // A: 4200 credit, 5000+1500+250 debit; B: 1800+800 credit, 2000 debit; owner: 3000 debit
        $this->assertSame(6800.0, $report['total_credit']);
        $this->assertSame(11750.0, $report['total_debit']);
        $this->assertSame(-4950.0, $report['balance']);
    }

    public function test_snapshot_excludes_activity_after_ownership_change(): void
    {
        $this->makeHaul($this->vehicleA, $this->routeA, '2026-09-05 08:00:00', 2000);

        $sold = Vehicle::factory()->ownedBy($this->otherOwner, '2026-01-01')->create([
            'plate' => '34 SLD 01',
        ]);

        Haul::factory()->create([
            'vehicle_id' => $sold->id,
            'party_id' => $this->owner->id,
            'route_id' => $this->routeA->id,
            'site_id' => $this->routeA->site_id,
            'dump_site_id' => $this->routeA->dump_site_id,
            'dump_party_id' => $this->dumpSite->party_id,
            'price' => 1500,
            'hauled_at' => '2026-09-10 08:00:00',
        ]);

        Haul::factory()->create([
            'vehicle_id' => $sold->id,
            'party_id' => $this->otherOwner->id,
            'route_id' => $this->routeA->id,
            'site_id' => $this->routeA->site_id,
            'dump_site_id' => $this->routeA->dump_site_id,
            'dump_party_id' => $this->dumpSite->party_id,
            'price' => 9999,
            'hauled_at' => '2026-09-20 08:00:00',
        ]);

        $report = $this->actingAs($this->admin)
            ->get(route('admin.reports.owner-monthly', [
                'owner' => $this->owner->id,
                'year' => 2026,
                'month' => 9,
            ]))
            ->assertOk()
            ->viewData('report');

        $plates = collect($report['vehicles'])->pluck('plate')->all();

        $this->assertSame(['34 ABR 69', '34 SLD 01'], $plates);
        $this->assertSame(2, $report['trips']);
        $this->assertSame(3500.0, $report['total_credit']);
    }

    public function test_carryover_from_prior_owner_ledger_is_included(): void
    {
        $haul = $this->makeHaul($this->vehicleA, $this->routeA, '2026-08-10 08:00:00', 5000);
        app(Ledger::class)->sync($haul, [
            LedgerLine::credit($this->owner->id, Carbon::parse('2026-08-10'), 5000, 'Önceki taşıma', $this->vehicleA->id),
        ]);

        $payment = $this->makeOwnerPayment('2026-08-20', 1000, 'Önceki sahip ödemesi');
        app(Ledger::class)->sync($payment, [
            LedgerLine::debit($this->owner->id, Carbon::parse('2026-08-20'), 1000, 'Önceki sahip ödemesi'),
        ]);

        $report = $this->actingAs($this->admin)
            ->get(route('admin.reports.owner-monthly', [
                'owner' => $this->owner->id,
                'year' => 2026,
                'month' => 9,
            ]))
            ->assertOk()
            ->viewData('report');

        $carryover = $report['carryover'];

        // debit 1000 - credit 5000 = -4000 → shown as 4000 credit
        $this->assertNull($carryover['debit']);
        $this->assertSame(4000.0, $carryover['credit']);
        $this->assertSame(4000.0, $report['total_credit']);
        $this->assertSame(0.0, $report['total_debit']);
        $this->assertSame(4000.0, $report['balance']);
    }

    public function test_date_range_filters_entries_inclusively(): void
    {
        $this->makeHaul($this->vehicleA, $this->routeA, '2026-09-01 00:00:00', 1000);
        $this->makeHaul($this->vehicleA, $this->routeA, '2026-09-10 12:00:00', 1200);
        $this->makeHaul($this->vehicleA, $this->routeA, '2026-09-15 23:30:00', 1300);
        $this->makeHaul($this->vehicleA, $this->routeA, '2026-09-16 00:00:00', 9999);

        $this->makeReceipt($this->vehicleA, '2026-09-01', 40, 50);
        $this->makeReceipt($this->vehicleA, '2026-09-15', 60, 50);
        $this->makeReceipt($this->vehicleA, '2026-09-16', 100, 50);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.reports.owner-monthly', [
                'owner' => $this->owner->id,
                'period' => 'range',
                'from' => '2026-09-01',
                'to' => '2026-09-15',
            ]));

        $response->assertOk()
            ->assertSee('Ahmet Yılmaz · 01.09.2026 – 15.09.2026', false);

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
            ->get(route('admin.reports.owner-monthly', [
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
        $this->makeHaul($this->vehicleA, $this->routeA, '2026-09-05 08:00:00', 2000);
        $this->makeReceipt($this->vehicleA, '2026-09-10', 100, 50);
        $this->makeOwnerPayment('2026-09-25', 500, 'Sahibe nakit');

        $response = $this->actingAs($this->admin)
            ->get(route('admin.reports.owner-monthly.export', [
                'owner' => $this->owner->id,
                'year' => 2026,
                'month' => 9,
            ]));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));

        $csv = $response->streamedContent();

        $this->assertStringContainsString('Açıklama', $csv);
        $this->assertStringContainsString('34 ABR 69', $csv);
        $this->assertStringContainsString('BAKIRKÖY GÜL İNŞ - 212', $csv);
        $this->assertStringContainsString('ALTUR', $csv);
        $this->assertStringContainsString('Sahibe nakit', $csv);
        $this->assertStringContainsString('Geçmiş Dönemler', $csv);
        $this->assertStringContainsString('Bakiye', $csv);
    }

    public function test_export_requires_an_owner(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.reports.owner-monthly.export', [
                'year' => 2026,
                'month' => 9,
            ]))
            ->assertRedirect(route('admin.reports.owner-monthly'))
            ->assertSessionHasErrors('owner');
    }

    public function test_unknown_owner_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.reports.owner-monthly', [
                'owner' => (string) Str::uuid(),
                'year' => 2026,
                'month' => 9,
            ]))
            ->assertRedirect(route('admin.reports.owner-monthly'))
            ->assertSessionHasErrors('owner');
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
            'party_id' => $vehicle->currentOwnership->party_id,
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
            'party_id' => $vehicle->currentOwnership->party_id,
            'amount' => $amount,
            'occurred_on' => $paidOn,
            'status' => 'paid',
            'paid_on' => $paidOn,
            'description' => $description,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeOwnerPayment(string $occurredOn, float $amount, string $description): PartyPayment
    {
        $this->owner->assignRole(PartyRole::VehicleOwner);

        return PartyPayment::query()->create([
            'party_id' => $this->owner->id,
            'payment_method' => PaymentMethod::Cash,
            'amount' => $amount,
            'occurred_on' => $occurredOn,
            'description' => $description,
        ]);
    }
}
