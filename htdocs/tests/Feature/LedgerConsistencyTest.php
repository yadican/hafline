<?php

namespace Tests\Feature;

use App\Enums\PartyRole;
use App\Models\DumpSite;
use App\Models\DumpSiteClassPrice;
use App\Models\FuelReceipt;
use App\Models\FuelStation;
use App\Models\Haul;
use App\Models\HaulRoute;
use App\Models\MachineWork;
use App\Models\Party;
use App\Models\RouteClassPrice;
use App\Models\Site;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleClass;
use App\Models\VehicleOwnership;
use App\Models\VehiclePayment;
use App\Models\VehicleReceivable;
use App\Repositories\VehicleAccountRepository;
use App\Services\Reports\CashMovementReportService;
use App\Services\Reports\OwnerBalanceReportService;
use App\Services\Reports\OwnerMonthlyReportService;
use App\Services\Reports\VehicleBalanceReportService;
use App\Services\Reports\VehicleMonthlyReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Walks real admin flows and checks after each step that every party's cari (ledger) is exactly
 * what its source documents say, and that the statement reports agree with the cari.
 */
class LedgerConsistencyTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Party $seller;

    private Party $buyer;

    private Vehicle $truck;

    private Vehicle $machine;

    private HaulRoute $route;

    private HaulRoute $otherRoute;

    private FuelStation $station;

    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->yonetici()->create();
        $this->seller = Party::factory()->person()->create(['name' => 'Satıcı Sahip']);
        $this->buyer = Party::factory()->create(['name' => 'Alıcı Sahip']);

        $this->truck = Vehicle::factory()->ownedBy($this->seller, '2026-01-01')->create(['plate' => '34 TR 100']);
        $this->machine = Vehicle::factory()
            ->ownedBy($this->seller, '2026-01-01')
            ->create(['plate' => '34 MK 100', 'vehicle_class_id' => VehicleClass::factory()->hourly()]);
        $this->machine->rates()->create(['hourly_rate' => 1000, 'started_on' => '2026-01-01']);

        $this->route = HaulRoute::factory()->create();
        $this->otherRoute = HaulRoute::factory()->create(['site_id' => $this->route->site_id]);
        $this->site = Site::query()->findOrFail($this->route->site_id);

        foreach ([$this->route, $this->otherRoute] as $route) {
            RouteClassPrice::query()->create(['route_id' => $route->id, 'vehicle_class_id' => $this->truck->vehicle_class_id, 'price' => 2500]);
            DumpSiteClassPrice::query()->create(['dump_site_id' => $route->dump_site_id, 'vehicle_class_id' => $this->truck->vehicle_class_id, 'price' => 400]);
        }

        $this->station = FuelStation::factory()->create(['unit_price' => 50]);
    }

    public function test_haul_edits_transfers_and_deletes_keep_every_cari_in_step(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.hauls.store'), $this->haulPayload(['trips' => 2, 'hauled_at' => '2026-03-10T10:00']))
            ->assertSessionHasNoErrors();
        $this->assertCariMatchesDocuments();

        [$first, $second] = Haul::query()->orderBy('id')->get()->all();

        $this->actingAs($this->admin)
            ->put(route('admin.hauls.update', $first), $this->haulPayload(['hauled_at' => '2026-03-20T10:00', 'tonnage' => 22]))
            ->assertSessionHasNoErrors();
        $this->actingAs($this->admin)
            ->put(route('admin.hauls.update', $second), $this->haulPayload([
                'hauled_at' => '2026-03-11T10:00',
                'dump_site_id' => $this->otherRoute->dump_site_id,
            ]))
            ->assertSessionHasNoErrors();
        $this->assertSame($this->otherRoute->dumpSite->party_id, $second->fresh()->dump_party_id);
        $this->assertCariMatchesDocuments();

        $this->transfer($this->truck, $this->buyer, '2026-03-15');
        $this->assertSame($this->buyer->id, $first->fresh()->party_id);
        $this->assertSame($this->seller->id, $second->fresh()->party_id);
        $this->assertCariMatchesDocuments();

        $this->actingAs($this->admin)->delete(route('admin.hauls.destroy', $second))->assertSessionHas('error');
        $this->actingAs($this->admin)
            ->post(route('admin.hauls.store'), $this->haulPayload(['ticket_no' => 'F-200', 'hauled_at' => '2026-03-12T10:00']))
            ->assertSessionHas('error');
        $this->assertNotSoftDeleted($second);
        $this->assertSame(2, Haul::query()->count());

        $this->undoLastTransfer($this->truck);
        $this->assertCariMatchesDocuments();

        $this->actingAs($this->admin)->delete(route('admin.hauls.destroy', $second))->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->delete(route('admin.hauls.destroy', $first))->assertSessionHasNoErrors();
        $this->assertCariMatchesDocuments();
        $this->assertSame(0.0, $this->cari($this->seller));
    }

    public function test_fuel_and_machine_edits_and_deletes_keep_every_cari_in_step(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.fuel-receipts.store'), $this->fuelPayload())
            ->assertSessionHasNoErrors();
        $receipt = FuelReceipt::query()->sole();

        $this->actingAs($this->admin)
            ->put(route('admin.fuel-receipts.update', $receipt), $this->fuelPayload(['quantity' => '55', 'unit_price' => '50']))
            ->assertSessionHasNoErrors();
        $this->assertSame('2750.00', $receipt->fresh()->amount);
        $this->assertCariMatchesDocuments();

        $operator = Party::factory()->person()->withRole(PartyRole::MachineOperator)->create();

        $this->actingAs($this->admin)
            ->post(route('admin.machine-works.store'), $this->machinePayload(['operator_id' => $operator->id, 'operator_rate' => '150']))
            ->assertSessionHasNoErrors();
        $work = MachineWork::query()->sole();
        $this->assertCariMatchesDocuments();

        $this->actingAs($this->admin)
            ->put(route('admin.machine-works.update', $work), $this->machinePayload(['hours' => '6', 'hourly_rate' => '1000']))
            ->assertSessionHasNoErrors();
        $this->assertCariMatchesDocuments();
        $this->assertSame(0.0, $this->cari($operator));

        $this->actingAs($this->admin)->delete(route('admin.fuel-receipts.destroy', $receipt))->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->delete(route('admin.machine-works.destroy', $work))->assertSessionHasNoErrors();
        $this->assertCariMatchesDocuments();
        $this->assertSame(0.0, $this->cari($this->seller));
    }

    public function test_cari_entry_edits_move_the_posting_with_the_entry(): void
    {
        $otherOwner = Party::factory()->create();
        Vehicle::factory()->ownedBy($otherOwner, '2026-01-01')->create(['plate' => '06 OT 100']);

        $this->actingAs($this->admin)
            ->post(route('admin.cari-entries.store'), $this->vehiclePaymentPayload())
            ->assertSessionHasNoErrors();
        $payment = VehiclePayment::query()->sole();
        $this->assertCariMatchesDocuments();

        $this->actingAs($this->admin)
            ->put(route('admin.cari-entries.update', ['kind' => 'vehicle_payment', 'entry' => $payment->id]), $this->vehiclePaymentPayload(['plate' => '06 OT 100', 'amount' => '1250']))
            ->assertSessionHasNoErrors();
        $this->assertSame($otherOwner->id, $payment->fresh()->party_id);
        $this->assertSame(0.0, $this->cari($this->seller));
        $this->assertSame(1250.0, $this->cari($otherOwner));
        $this->assertCariMatchesDocuments();

        $otherDump = DumpSite::factory()->create();

        $this->actingAs($this->admin)
            ->post(route('admin.cari-entries.store'), $this->dumpPaymentPayload($this->route->dump_site_id))
            ->assertSessionHasNoErrors();
        $dumpPayment = DB::table('dump_site_payments')->sole();

        $this->actingAs($this->admin)
            ->put(route('admin.cari-entries.update', ['kind' => 'dump_payment', 'entry' => $dumpPayment->id]), $this->dumpPaymentPayload($otherDump->id))
            ->assertSessionHasNoErrors();
        $this->assertSame(0.0, $this->cari($this->route->dumpSite->party));
        $this->assertSame(700.0, $this->cari($otherDump->party));
        $this->assertCariMatchesDocuments();

        $this->actingAs($this->admin)
            ->delete(route('admin.cari-entries.destroy', ['kind' => 'vehicle_payment', 'entry' => $payment->id]))
            ->assertSessionHasNoErrors();
        $this->assertSame(0.0, $this->cari($otherOwner));
        $this->assertCariMatchesDocuments();
    }

    public function test_back_dated_transfer_moves_the_work_keeps_payments_and_closes_the_previous_owner(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.hauls.store'), $this->haulPayload(['hauled_at' => '2026-03-20T10:00']))
            ->assertSessionHasNoErrors();
        $this->actingAs($this->admin)
            ->post(route('admin.cari-entries.store'), $this->vehiclePaymentPayload(['occurred_on' => '2026-03-21']))
            ->assertSessionHasNoErrors();
        $this->actingAs($this->admin)
            ->post(route('admin.cari-entries.store'), [...$this->vehiclePaymentPayload(['occurred_on' => '2026-03-22', 'amount' => '300']), 'kind' => 'vehicle_receivable'])
            ->assertSessionHasNoErrors();
        $payment = VehiclePayment::query()->sole();

        $this->transfer($this->truck, $this->buyer, '2026-03-15');

        $this->assertSame($this->buyer->id, Haul::query()->sole()->party_id);
        $this->assertSame($this->buyer->id, VehicleReceivable::query()->sole()->party_id, 'İşin ek hakedişi işle birlikte yeni sahibe geçer.');
        $this->assertSame($this->seller->id, $payment->fresh()->party_id, 'Ödeme parayı alan eski sahipte görünür.');

        // Satıcı 1000 ödeme aldı ama işi alıcıya geçti: 1000 borcu devir kapanışıyla alıcıya aktarılır.
        $this->assertSame('-1000.00', $this->truck->fresh()->currentOwnership->carried_balance);
        $this->assertSame(0.0, $this->cari($this->seller));
        $this->assertSame(-1800.0, $this->cari($this->buyer));
        $this->assertCariMatchesDocuments();

        $this->actingAs($this->admin)
            ->put(route('admin.cari-entries.update', ['kind' => 'vehicle_payment', 'entry' => $payment->id]), $this->vehiclePaymentPayload(['occurred_on' => '2026-03-21', 'amount' => '900']))
            ->assertSessionHas('error');
        $this->assertSame('1000.00', $payment->fresh()->amount, 'Kapanmış eski sahip ödemesi değiştirilemez.');

        $this->undoLastTransfer($this->truck);
        $this->assertSame($this->seller->id, Haul::query()->sole()->party_id);
        $this->assertSame($this->seller->id, VehicleReceivable::query()->sole()->party_id);
        $this->assertSame(-1800.0, $this->cari($this->seller));
        $this->assertSame(0.0, $this->cari($this->buyer));
        $this->assertCariMatchesDocuments();
    }

    public function test_settled_transfer_requires_the_previous_owner_to_be_paid_off_first(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.hauls.store'), $this->haulPayload(['trips' => 2, 'hauled_at' => '2026-03-10T10:00']))
            ->assertSessionHasNoErrors();

        $this->actingAs($this->admin)
            ->post(route('admin.vehicles.ownerships.store', $this->truck), ['party_id' => $this->buyer->id, 'started_on' => '2026-03-15', 'settlement' => 'settled'])
            ->assertSessionHas('error', 'Eski sahibin bu plakada 5.000,00 TL alacağı var; önce ödeme veya tahsilatla kapatın ya da bakiyeyi yeni sahibe aktarın.');
        $this->assertSame(1, $this->truck->ownerships()->count());

        $this->actingAs($this->admin)
            ->post(route('admin.cari-entries.store'), $this->vehiclePaymentPayload(['occurred_on' => '2026-03-14', 'amount' => '5000', 'payment_method' => 'offset']))
            ->assertSessionHasNoErrors();

        $this->actingAs($this->admin)
            ->post(route('admin.vehicles.ownerships.store', $this->truck), ['party_id' => $this->buyer->id, 'started_on' => '2026-03-15', 'settlement' => 'settled'])
            ->assertSessionHas('success');

        $this->assertSame('0.00', $this->truck->fresh()->currentOwnership->carried_balance);
        $this->assertSame(0.0, $this->cari($this->seller));
        $this->assertSame(0.0, $this->cari($this->buyer));
        $this->assertCariMatchesDocuments();
    }

    public function test_carried_balance_shows_on_the_plate_and_owner_reports_without_touching_the_cash_drawer(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.hauls.store'), $this->haulPayload(['trips' => 2, 'hauled_at' => '2026-03-10T10:00']))
            ->assertSessionHasNoErrors();
        $this->transfer($this->truck, $this->buyer, '2026-03-15');

        $plate = app(VehicleMonthlyReportService::class)->report(['vehicle' => $this->truck->id, 'period' => 'month', 'year' => 2026, 'month' => 3]);
        $transfers = collect($plate['rows'])->where('type', 'transfer')->values();
        $this->assertCount(2, $transfers);
        $this->assertSame(5000.0, $transfers->sum('debit'));
        $this->assertSame(5000.0, $transfers->sum('credit'));
        $this->assertSame(5000.0, $plate['balance'], 'Plakanın bakiyesi devirle değişmez, yalnızca sahibi değişir.');

        $seller = app(OwnerMonthlyReportService::class)->report(['owner' => $this->seller->id, 'period' => 'month', 'year' => 2026, 'month' => 3]);
        $buyer = app(OwnerMonthlyReportService::class)->report(['owner' => $this->buyer->id, 'period' => 'month', 'year' => 2026, 'month' => 3]);
        $this->assertSame(0.0, $seller['balance']);
        $this->assertSame(5000.0, $buyer['balance']);
        $this->assertStringStartsWith('Devir kapanışı · 34 TR 100', collect($seller['vehicles'][0]['rows'])->firstWhere('type', 'transfer')['description']);
        $this->assertStringStartsWith('Devralınan bakiye · 34 TR 100', collect($buyer['vehicles'][0]['rows'])->firstWhere('type', 'transfer')['description']);

        $april = app(OwnerMonthlyReportService::class)->report(['owner' => $this->buyer->id, 'period' => 'month', 'year' => 2026, 'month' => 4]);
        $this->assertSame(5000.0, $april['carryover']['credit']);

        $balances = collect(app(OwnerBalanceReportService::class)->report(['period' => 'range', 'from' => '2026-01-01', 'to' => '2026-04-30', 'site' => '', 'fuel_station' => ''])['rows'])->keyBy('name');
        $this->assertSame(0.0, $balances[$this->seller->name]['balance']);
        $this->assertSame(-5000.0, $balances[$this->buyer->name]['balance']);

        $cash = app(CashMovementReportService::class)->report(['period' => 'month', 'year' => 2026, 'month' => 3]);
        $this->assertSame([], $cash['lines']);
    }

    public function test_dump_operator_change_keeps_each_movement_with_the_operator_it_happened_with(): void
    {
        $dumpSite = $this->route->dumpSite;
        $oldOperator = $dumpSite->party;
        $newOperator = Party::factory()->company()->create();

        $this->actingAs($this->admin)
            ->post(route('admin.hauls.store'), $this->haulPayload(['hauled_at' => '2026-03-10T10:00']))
            ->assertSessionHasNoErrors();
        $this->actingAs($this->admin)
            ->post(route('admin.cari-entries.store'), $this->dumpPaymentPayload($dumpSite->id, '150'))
            ->assertSessionHasNoErrors();
        $oldPayment = DB::table('dump_site_payments')->sole();

        $this->actingAs($this->admin)
            ->put(route('admin.dump-sites.update', $dumpSite), ['name' => $dumpSite->name, 'party_id' => $newOperator->id, 'active' => '1'])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->admin)
            ->post(route('admin.hauls.store'), $this->haulPayload(['ticket_no' => 'F-101', 'hauled_at' => '2026-03-20T10:00']))
            ->assertSessionHasNoErrors();
        $this->actingAs($this->admin)
            ->post(route('admin.cari-entries.store'), $this->dumpPaymentPayload($dumpSite->id, '400'))
            ->assertSessionHasNoErrors();

        $this->assertSame(-250.0, $this->cari($oldOperator));
        $this->assertSame(0.0, $this->cari($newOperator));

        $this->actingAs($this->admin)
            ->put(route('admin.cari-entries.update', ['kind' => 'dump_payment', 'entry' => $oldPayment->id]), [...$this->dumpPaymentPayload($dumpSite->id, '150'), 'description' => 'Düzeltme'])
            ->assertSessionHasNoErrors();

        $this->assertSame(-250.0, $this->cari($oldOperator), 'Açıklama düzeltmesi eski işletmeciye yapılan ödemeyi yeni işletmeciye taşıdı.');
        $this->assertSame(0.0, $this->cari($newOperator));
        $this->assertCariMatchesDocuments();
    }

    public function test_fuel_receipt_edit_without_a_price_keeps_its_original_price(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.fuel-receipts.store'), $this->fuelPayload())
            ->assertSessionHasNoErrors();
        $receipt = FuelReceipt::query()->sole();

        $this->station->update(['unit_price' => 65]);

        $this->actingAs($this->admin)
            ->put(route('admin.fuel-receipts.update', $receipt), $this->fuelPayload(['km' => '120500']))
            ->assertSessionHasNoErrors();

        $this->assertSame('50.0000', $receipt->fresh()->unit_price);
        $this->assertSame('2000.00', $receipt->fresh()->amount);
    }

    public function test_owner_statement_reports_agree_with_the_owner_cari(): void
    {
        $this->seller->assignRole(PartyRole::VehicleOwner);
        $this->seller->assignRole(PartyRole::MachineOperator);

        $this->actingAs($this->admin)
            ->post(route('admin.hauls.store'), $this->haulPayload(['trips' => 3, 'hauled_at' => '2026-03-10T10:00']))
            ->assertSessionHasNoErrors();
        $this->actingAs($this->admin)
            ->post(route('admin.fuel-receipts.store'), $this->fuelPayload(['issued_on' => '2026-03-12']))
            ->assertSessionHasNoErrors();
        $this->actingAs($this->admin)
            ->post(route('admin.machine-works.store'), $this->machinePayload(['worked_on' => '2026-03-14']))
            ->assertSessionHasNoErrors();
        $this->actingAs($this->admin)
            ->post(route('admin.cari-entries.store'), $this->vehiclePaymentPayload(['occurred_on' => '2026-03-16']))
            ->assertSessionHasNoErrors();
        $this->actingAs($this->admin)
            ->post(route('admin.cari-entries.store'), ['kind' => 'owner_payment', 'party_id' => $this->seller->id, 'amount' => '500', 'occurred_on' => '2026-03-25', 'payment_method' => 'cash'])
            ->assertSessionHasNoErrors();

        $otherMachine = Vehicle::factory()
            ->ownedBy($this->buyer, '2026-01-01')
            ->create(['plate' => '34 MK 200', 'vehicle_class_id' => VehicleClass::factory()->hourly()]);
        $otherMachine->rates()->create(['hourly_rate' => 900, 'started_on' => '2026-01-01']);
        $this->actingAs($this->admin)
            ->post(route('admin.machine-works.store'), $this->machinePayload(['ticket_no' => 'M-200', 'plate' => '34 MK 200', 'worked_on' => '2026-03-20', 'hours' => '8', 'operator_id' => $this->seller->id, 'operator_rate' => '150']))
            ->assertSessionHasNoErrors();

        // 3 × 2500 sefer + 8 × 1000 makine + 8 × 150 operatörlük − 2000 yakıt − 1000 plaka ödeme − 500 sahip ödemesi
        $cari = -$this->cari($this->seller);
        $this->assertSame(13200.0, $cari);
        $vehicleShare = $cari - 1200.0 + 500.0;

        $monthly = app(OwnerMonthlyReportService::class)->report(['owner' => $this->seller->id, 'period' => 'month', 'year' => 2026, 'month' => 4]);
        $this->assertSame($cari, $monthly['balance'], 'Plaka sahibi aylık raporu cari ile tutmuyor.');

        $this->actingAs($this->admin)
            ->get(route('admin.reports.owner-monthly', ['owner' => $this->seller->id, 'year' => 2026, 'month' => 3]))
            ->assertOk()
            ->assertSee($this->site->name.' - Makine çalışması', false)
            ->assertSee('34 MK 200 · '.$this->site->name.' - Operatörlük', false)
            ->assertSee('8,00 saat', false);

        $balance = collect(app(OwnerBalanceReportService::class)->report(['period' => 'range', 'from' => '2026-01-01', 'to' => '2026-04-30', 'site' => '', 'fuel_station' => ''])['rows'])
            ->firstWhere('name', $this->seller->name)['balance'];
        $this->assertSame($cari, -$balance, 'Plaka sahibi bakiye raporu cari ile tutmuyor.');

        $vehicleBalances = collect([$this->truck, $this->machine])
            ->sum(fn (Vehicle $vehicle) => app(VehicleMonthlyReportService::class)->report(['vehicle' => $vehicle->id, 'period' => 'month', 'year' => 2026, 'month' => 4])['balance']);
        $this->assertSame($vehicleShare, round($vehicleBalances, 2), 'Plaka aylık raporları, sahibin araç hareketleriyle tutmuyor.');

        $plateBalances = collect(app(VehicleBalanceReportService::class)->report(['period' => 'range', 'from' => '2026-01-01', 'to' => '2026-04-30', 'site' => '', 'fuel_station' => ''])['rows'])
            ->whereIn('plate', ['34 TR 100', '34 MK 100'])
            ->sum('balance');
        $this->assertSame($vehicleShare, round(-$plateBalances, 2), 'Plaka bakiye raporu, sahibin araç hareketleriyle tutmuyor.');
    }

    /**
     * Live ledger lines must equal what the source documents say, line for line, and every work
     * document (haul, fuel, machine) must sit on the account of the vehicle's owner on its date.
     * Payments stay on whoever was paid, so their party is not derived from today's ownership, and
     * every transfer must leave the previous owner's balance on the plate at zero.
     */
    private function assertCariMatchesDocuments(): void
    {
        $expected = <<<'SQL'
            select 'haul' st, id sid, party_id p, date(hauled_at) d, 0 dr, price cr from hauls where deleted_at is null and price <> 0
            union all select 'haul', id, dump_party_id, date(hauled_at), 0, dump_price from hauls where deleted_at is null and dump_price <> 0
            union all select 'fuel_receipt', id, party_id, issued_on, amount, 0 from fuel_receipts where deleted_at is null and amount <> 0
            union all select 'machine_work', id, party_id, worked_on, 0, amount from machine_works where deleted_at is null and amount <> 0
            union all select 'machine_work', id, operator_id, worked_on, 0, operator_amount from machine_works where deleted_at is null and operator_amount is not null and operator_amount <> 0
            union all select 'vehicle_payment', id, party_id, occurred_on, amount, 0 from vehicle_payments where deleted_at is null
            union all select 'vehicle_receivable', id, party_id, occurred_on, 0, amount from vehicle_receivables where deleted_at is null
            union all select 'party_payment', id, party_id, occurred_on, amount, 0 from party_payments where deleted_at is null
            union all select 'dump_site_payment', id, '', occurred_on, amount, 0 from dump_site_payments where deleted_at is null
            union all select 'company_transaction', id, party_id, occurred_on, debit, credit from company_transactions where deleted_at is null and (debit <> 0 or credit <> 0)
            union all select 'vehicle_ownership', id, carried_from_party_id, started_on, case when carried_balance > 0 then carried_balance else 0 end, case when carried_balance < 0 then -carried_balance else 0 end from vehicle_ownerships where carried_balance <> 0
            union all select 'vehicle_ownership', id, party_id, started_on, case when carried_balance < 0 then -carried_balance else 0 end, case when carried_balance > 0 then carried_balance else 0 end from vehicle_ownerships where carried_balance <> 0
        SQL;
        $live = <<<'SQL'
            select source_type st, source_id sid, case when source_type = 'dump_site_payment' then '' else party_id end p, occurred_on d, debit dr, credit cr from ledger_entries le
            where reversal_of is null and not exists (select 1 from ledger_entries r where r.reversal_of = le.id)
        SQL;

        $lines = fn (string $sql): array => collect(DB::select($sql))
            ->map(fn (object $row): string => implode('|', [
                $row->st,
                $row->sid,
                $row->p,
                substr((string) $row->d, 0, 10),
                number_format((float) $row->dr, 2, '.', ''),
                number_format((float) $row->cr, 2, '.', ''),
            ]))
            ->sort()
            ->values()
            ->all();

        $this->assertSame($lines($expected), $lines($live), 'Cari satırları kaynak belgelerle birebir tutmuyor.');

        foreach (['hauls' => 'date(x.hauled_at)', 'fuel_receipts' => 'x.issued_on', 'machine_works' => 'x.worked_on'] as $table => $date) {
            $wrongOwner = (int) DB::scalar(<<<SQL
                select count(*) from {$table} x where deleted_at is null and not exists (
                    select 1 from vehicle_ownerships o where o.vehicle_id = x.vehicle_id and o.party_id = x.party_id
                      and o.started_on <= {$date} and (o.ended_on is null or o.ended_on >= {$date}))
            SQL);

            $this->assertSame(0, $wrongOwner, "{$table}: kayıt o tarihteki araç sahibinin carisinde değil.");
        }

        foreach (VehicleOwnership::query()->whereNotNull('carried_from_party_id')->get() as $ownership) {
            $this->assertSame(
                0.0,
                app(VehicleAccountRepository::class)->balance($ownership->vehicle_id, $ownership->carried_from_party_id),
                'Devredilen plakada eski sahibin bakiyesi kapanmamış.',
            );
        }
    }

    /**
     * Debit − credit on the party's cari; negative means the company owes the party.
     */
    private function cari(Party $party): float
    {
        return round((float) DB::table('ledger_entries')->where('party_id', $party->id)->sum(DB::raw('debit - credit')), 2);
    }

    private function transfer(Vehicle $vehicle, Party $party, string $on): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.vehicles.ownerships.store', $vehicle), ['party_id' => $party->id, 'started_on' => $on, 'settlement' => 'carry'])
            ->assertSessionHas('success');
    }

    private function undoLastTransfer(Vehicle $vehicle): void
    {
        $this->actingAs($this->admin)
            ->delete(route('admin.vehicles.ownerships.destroy', [$vehicle, $vehicle->fresh()->currentOwnership]))
            ->assertSessionHas('success');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function haulPayload(array $overrides = []): array
    {
        return [
            'ticket_no' => 'F-100',
            'plate' => '34 TR 100',
            'trips' => 1,
            'hauled_at' => '2026-03-10T10:00',
            'site_id' => $this->route->site_id,
            'dump_site_id' => $this->route->dump_site_id,
            'tonnage' => 18,
            'shift' => 'day',
            ...$overrides,
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function fuelPayload(array $overrides = []): array
    {
        return [
            'ticket_no' => 'Y-100',
            'plate' => '34 TR 100',
            'issued_on' => '2026-03-12',
            'shift' => 'day',
            'fuel_station_id' => $this->station->id,
            'site_id' => $this->site->id,
            'quantity' => '40',
            'unit_price' => '',
            'km' => '120000',
            'received_by' => 'Ali',
            'haul_ticket_count' => '3',
            ...$overrides,
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function machinePayload(array $overrides = []): array
    {
        return [
            'ticket_no' => 'M-100',
            'plate' => '34 MK 100',
            'worked_on' => '2026-03-14',
            'shift' => 'day',
            'site_id' => $this->site->id,
            'operator_id' => '',
            'hours' => '8',
            'hourly_rate' => '',
            ...$overrides,
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function vehiclePaymentPayload(array $overrides = []): array
    {
        return [
            'kind' => 'vehicle_payment',
            'plate' => '34 TR 100',
            'amount' => '1000',
            'occurred_on' => '2026-03-16',
            'payment_method' => 'cash',
            ...$overrides,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function dumpPaymentPayload(string $dumpSiteId, string $amount = '700'): array
    {
        return [
            'kind' => 'dump_payment',
            'dump_site_id' => $dumpSiteId,
            'amount' => $amount,
            'occurred_on' => '2026-03-18',
            'payment_method' => 'bank_transfer',
        ];
    }
}
