<?php

namespace Tests\Feature\Admin;

use App\Enums\PaymentMethod;
use App\Models\DumpSite;
use App\Models\DumpSitePayment;
use App\Models\Haul;
use App\Models\HaulRoute;
use App\Models\Site;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleClass;
use App\Services\Ledger\HaulLedger;
use App\Services\Ledger\Ledger;
use App\Services\Ledger\LedgerLine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DumpSitePaymentReportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private DumpSite $dumpSite;

    private HaulRoute $route;

    private VehicleClass $tir;

    private VehicleClass $makine;

    private Vehicle $vehicleTir;

    private Vehicle $vehicleMakine;

    private Vehicle $vehicleSinifsiz;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->yonetici()->create();
        $this->dumpSite = DumpSite::factory()->create(['name' => '212']);
        $site = Site::factory()->create(['name' => 'TOPKAPI']);
        $this->route = HaulRoute::factory()->create([
            'site_id' => $site->id,
            'dump_site_id' => $this->dumpSite->id,
        ]);

        $this->tir = VehicleClass::factory()->create(['name' => 'TIR']);
        $this->makine = VehicleClass::factory()->create(['name' => 'MAKİNE']);

        $this->vehicleTir = Vehicle::factory()->create([
            'plate' => '34 TIR 01',
            'vehicle_class_id' => $this->tir->id,
        ]);
        $this->vehicleMakine = Vehicle::factory()->create([
            'plate' => '34 MAK 01',
            'vehicle_class_id' => $this->makine->id,
        ]);
        $this->vehicleSinifsiz = Vehicle::factory()->create([
            'plate' => '34 SIN 01',
            'vehicle_class_id' => null,
        ]);
    }

    public function test_guest_is_redirected(): void
    {
        $this->get(route('admin.reports.dump-site-payments'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_page_requires_dump_site(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.reports.dump-site-payments'))
            ->assertOk()
            ->assertViewHas('ready', false);

        $this->actingAs($this->admin)
            ->get(route('admin.reports.dump-site-payments', [
                'period' => 'month',
                'year' => 2026,
                'month' => 9,
            ]))
            ->assertRedirect(route('admin.reports.dump-site-payments'))
            ->assertSessionHasErrors(['dump_site']);
    }

    public function test_report_groups_by_class_includes_unclassified_and_ledger_balance(): void
    {
        $this->makeHaul($this->vehicleTir, '2026-08-20 10:00:00', 10, 1000);
        $this->makeHaul($this->vehicleTir, '2026-09-05 10:00:00', 20, 500);
        $this->makeHaul($this->vehicleTir, '2026-09-30 18:00:00', 15, 300);
        $this->makeHaul($this->vehicleMakine, '2026-09-10 10:00:00', 5, 200);
        $this->makeHaul($this->vehicleSinifsiz, '2026-09-12 10:00:00', 8, 150);

        $this->makePayment('2026-08-25', 400, 'Eski ödeme');
        $this->makePayment('2026-09-15', 250, 'Dönem ödeme', PaymentMethod::Cheque, 'CK-1');

        $report = $this->actingAs($this->admin)
            ->get(route('admin.reports.dump-site-payments', $this->filters()))
            ->assertOk()
            ->assertSee('212 Döküm Yeri Ödeme Raporu', false)
            ->assertSee('Sınıfsız', false)
            ->assertSee('CK-1', false)
            ->viewData('report');

        $byClass = collect($report['dump_rows'])->keyBy('class_name');

        $this->assertSame(2, $byClass['TIR']['trips']);
        $this->assertSame(35.0, $byClass['TIR']['tonnage']);
        $this->assertSame(800.0, $byClass['TIR']['amount']);

        $this->assertSame(1, $byClass['MAKİNE']['trips']);
        $this->assertSame(200.0, $byClass['MAKİNE']['amount']);

        $this->assertSame(1, $byClass['Sınıfsız']['trips']);
        $this->assertSame(150.0, $byClass['Sınıfsız']['amount']);

        $this->assertSame(4, $report['total_trips']);
        $this->assertSame(1150.0, $report['total_dump_amount']);
        $this->assertSame(250.0, $report['total_payment_amount']);

        $this->assertSame(1000.0, $report['opening_dump']);
        $this->assertSame(400.0, $report['opening_payment']);
        $this->assertSame(600.0, $report['opening']);
        $this->assertSame(1500.0, $report['balance']);
    }

    public function test_soft_deleted_excluded(): void
    {
        $haul = $this->makeHaul($this->vehicleTir, '2026-09-05 10:00:00', 10, 500);
        $haul->delete();
        app(HaulLedger::class)->void($haul);

        $this->makeHaul($this->vehicleTir, '2026-09-06 10:00:00', 10, 200);

        $payment = $this->makePayment('2026-09-07', 50, 'Silinecek');
        app(Ledger::class)->void($payment);
        $payment->delete();

        $report = $this->actingAs($this->admin)
            ->get(route('admin.reports.dump-site-payments', $this->filters()))
            ->viewData('report');

        $this->assertSame(1, $report['total_trips']);
        $this->assertSame(200.0, $report['total_dump_amount']);
        $this->assertSame(0.0, $report['total_payment_amount']);
        $this->assertSame(200.0, $report['balance']);
    }

    public function test_csv_export(): void
    {
        $this->makeHaul($this->vehicleTir, '2026-09-05 10:00:00', 10, 500);
        $this->makePayment('2026-09-10', 100, 'Test');

        $csv = $this->actingAs($this->admin)
            ->get(route('admin.reports.dump-site-payments.export', $this->filters()))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('Araç Tipi', $csv);
        $this->assertStringContainsString('Toplam Döküm', $csv);
        $this->assertStringContainsString('Bakiye', $csv);
        $this->assertStringContainsString('500,00', $csv);
    }

    /**
     * @return array{period: string, year: int, month: int, dump_site: string}
     */
    private function filters(): array
    {
        return [
            'period' => 'month',
            'year' => 2026,
            'month' => 9,
            'dump_site' => $this->dumpSite->id,
        ];
    }

    private function makeHaul(Vehicle $vehicle, string $hauledAt, float $tonnage, float $dumpPrice): Haul
    {
        $haul = Haul::factory()->create([
            'vehicle_id' => $vehicle->id,
            'party_id' => $vehicle->currentOwnership->party_id,
            'route_id' => $this->route->id,
            'site_id' => $this->route->site_id,
            'dump_site_id' => $this->dumpSite->id,
            'dump_party_id' => $this->dumpSite->party_id,
            'tonnage' => $tonnage,
            'dump_price' => $dumpPrice,
            'hauled_at' => $hauledAt,
        ]);

        app(HaulLedger::class)->sync($haul);

        return $haul;
    }

    private function makePayment(
        string $occurredOn,
        float $amount,
        string $description,
        PaymentMethod $method = PaymentMethod::Cash,
        ?string $chequeNo = null,
    ): DumpSitePayment {
        $payment = DumpSitePayment::query()->create([
            'dump_site_id' => $this->dumpSite->id,
            'payment_method' => $method,
            'amount' => $amount,
            'occurred_on' => $occurredOn,
            'cheque_no' => $chequeNo,
            'description' => $description,
        ]);

        app(Ledger::class)->sync($payment, [
            LedgerLine::debit(
                $this->dumpSite->party_id,
                Carbon::parse($occurredOn),
                $amount,
                $description,
            )->paidBy($method),
        ]);

        return $payment;
    }
}
