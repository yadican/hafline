<?php

namespace Tests\Feature\Admin;

use App\Enums\AccountType;
use App\Enums\CariEntryKind;
use App\Enums\PartyRole;
use App\Models\AccountTransfer;
use App\Models\Bank;
use App\Models\CompanyTransaction;
use App\Models\DumpSite;
use App\Models\DumpSiteClassPrice;
use App\Models\DumpSitePayment;
use App\Models\Employee;
use App\Models\EmployeeBonus;
use App\Models\EmployeePayment;
use App\Models\FinancialInstrument;
use App\Models\FuelReceipt;
use App\Models\FuelStation;
use App\Models\FuelTankFill;
use App\Models\Haul;
use App\Models\HaulRoute;
use App\Models\MachineWork;
use App\Models\OpeningBalance;
use App\Models\Party;
use App\Models\PartyPayment;
use App\Models\PartyTransfer;
use App\Models\RouteClassPrice;
use App\Models\Shipment;
use App\Models\Site;
use App\Models\SiteBill;
use App\Models\SiteTransaction;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleClass;
use App\Models\VehiclePayment;
use App\Models\VehicleReceivable;
use App\Services\Ledger\LedgerAudit;
use App\Services\PartyService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Throws a long random mix of cari entries, edits, deletions, cheque steps, plate transfers and
 * site cari changes at the system and checks after every step that the cari still agrees with
 * its documents. Rejected operations are fine; a posting that leaks is not.
 */
class CariFuzzTest extends TestCase
{
    use RefreshDatabase;

    private const OPERATIONS = 200;

    private User $admin;

    private Party $temporary;

    /** @var array<string, list<Party>> */
    private array $parties = [];

    /** @var list<Vehicle> */
    private array $vehicles = [];

    /** @var list<Site> */
    private array $sites = [];

    private DumpSite $dumpSite;

    private HaulRoute $route;

    /** @var list<FuelStation> */
    private array $stations = [];

    private Party $operator;

    /** @var list<Employee> */
    private array $employees = [];

    private Bank $cashBox;

    private Bank $bank;

    /** @var array<string, int> */
    private array $done = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-29 12:00:00');
        $this->admin = User::factory()->yonetici()->create();
        $this->temporary = app(PartyService::class)->temporaryParty();

        foreach ([PartyRole::Customer, PartyRole::Supplier, PartyRole::Fuel, PartyRole::Market, PartyRole::VehicleOwner] as $role) {
            $this->parties[$role->value] = Party::factory()->company()->withRole($role)->count(2)->create()->all();
        }

        $truckClass = VehicleClass::factory()->create();
        $this->vehicles = [
            Vehicle::factory()->ownedBy($this->parties['vehicle_owner'][0], '2026-01-01')->create(['plate' => '34 FZ 001', 'vehicle_class_id' => $truckClass->id]),
            Vehicle::factory()->ownedBy($this->parties['vehicle_owner'][1], '2026-01-01')->create(['plate' => '34 FZ 002', 'vehicle_class_id' => $truckClass->id]),
            Vehicle::factory()->ownedBy($this->temporary, '2026-01-01')->create(['plate' => '34 FZ 003', 'vehicle_class_id' => $truckClass->id]),
            Vehicle::factory()->ownedBy($this->parties['vehicle_owner'][0], '2026-01-01')->create(['plate' => '34 FZ 900', 'vehicle_class_id' => VehicleClass::factory()->hourly()]),
        ];
        $this->vehicles[3]->rates()->create(['hourly_rate' => 1000, 'started_on' => '2026-01-01']);

        $this->sites = [
            Site::factory()->create(['party_id' => $this->parties['customer'][0]->id]),
            Site::factory()->create(['party_id' => null]),
        ];
        $this->dumpSite = DumpSite::factory()->create();
        $this->route = HaulRoute::factory()->create(['site_id' => $this->sites[0]->id, 'dump_site_id' => $this->dumpSite->id]);
        RouteClassPrice::query()->create(['route_id' => $this->route->id, 'vehicle_class_id' => $truckClass->id, 'price' => 2500]);
        DumpSiteClassPrice::query()->create(['dump_site_id' => $this->dumpSite->id, 'vehicle_class_id' => $truckClass->id, 'price' => 400]);

        $this->stations = [
            FuelStation::factory()->create(['unit_price' => 50, 'purchase_price' => 42]),
            FuelStation::factory()->tank()->create(['unit_price' => 50]),
        ];
        $this->operator = Party::factory()->withRole(PartyRole::MachineOperator)->create();
        $this->employees = Employee::factory()->count(2)->create(['salary' => 30000])->all();
        $this->cashBox = Bank::factory()->create(['type' => AccountType::Cash, 'opening_balance' => 0]);
        $this->bank = Bank::factory()->create(['type' => AccountType::Bank, 'opening_balance' => 0]);
    }

    /**
     * CARI_FUZZ_SEEDS=200 runs that many extra seeds for a long hunt.
     *
     * @return array<string, array{int}>
     */
    public static function seeds(): array
    {
        $seeds = [7, 1907, 424242, 31337, ...range(1000, 999 + (int) getenv('CARI_FUZZ_SEEDS'))];

        return collect($seeds)->mapWithKeys(fn (int $seed) => ["seed {$seed}" => [$seed]])->all();
    }

    #[DataProvider('seeds')]
    public function test_random_operations_never_leak_from_the_cari(int $seed): void
    {
        mt_srand($seed);

        for ($step = 1; $step <= self::OPERATIONS; $step++) {
            $operation = $this->pick([
                'create', 'create', 'create', 'create', 'update', 'update', 'delete',
                'step', 'step', 'step', 'undo', 'owner', 'owner', 'site',
                'haul', 'haul', 'machine', 'fuel', 'fuel', 'shipment', 'fill', 'payroll', 'payroll', 'document',
            ]);
            $description = $this->{$operation.'Operation'}();

            $this->assertNoLeak("adım {$step} ({$description}), tohum {$seed}");
        }

        $this->assertGreaterThan(10, $this->done['create'] ?? 0, 'Yeterli cari hareketi oluşmadı: '.json_encode($this->done));
        $this->assertGreaterThan(5, ($this->done['haul'] ?? 0) + ($this->done['fuel'] ?? 0) + ($this->done['machine'] ?? 0) + ($this->done['shipment'] ?? 0), 'Yeterli iş belgesi oluşmadı: '.json_encode($this->done));
        $this->assertGreaterThan(2, $this->done['step'] ?? 0, 'Yeterli çek işlemi olmadı: '.json_encode($this->done));
        $this->assertGreaterThan(0, FinancialInstrument::query()->count());

        $exit = Artisan::call('ledger:audit');
        $output = Artisan::output();
        $this->assertSame(0, $exit, "tohum {$seed}: ".collect(explode("\n", $output))->filter(fn (string $line) => str_contains($line, '✗') || str_contains($line, '  '))->implode("\n"));
    }

    private function createOperation(): string
    {
        $payload = $this->payload($this->pick(CariEntryKind::cases()));
        $this->attempt('create', fn () => $this->actingAs($this->admin)->post(route('admin.cari-entries.store'), $payload));

        return 'yeni '.$payload['kind'].' '.($payload['payment_method'] ?? '-');
    }

    private function updateOperation(): string
    {
        [$kind, $id] = $this->existingEntry() ?? [null, null];

        if (! $kind) {
            return 'düzenlenecek kayıt yok';
        }

        $payload = $this->payload($kind);
        $this->attempt('update', fn () => $this->actingAs($this->admin)->put(route('admin.cari-entries.update', ['kind' => $kind->value, 'entry' => $id]), $payload));

        return 'düzenle '.$kind->value;
    }

    private function deleteOperation(): string
    {
        [$kind, $id] = $this->existingEntry() ?? [null, null];

        if (! $kind) {
            return 'silinecek kayıt yok';
        }

        $this->attempt('delete', fn () => $this->actingAs($this->admin)->delete(route('admin.cari-entries.destroy', ['kind' => $kind->value, 'entry' => $id])));

        return 'sil '.$kind->value;
    }

    private function stepOperation(): string
    {
        $instrument = $this->pickRow(FinancialInstrument::query());

        if (! $instrument) {
            return 'çek yok';
        }

        $allowed = array_map(fn ($type) => $type->value, $instrument->status->allows());
        $type = $allowed !== [] && mt_rand(1, 5) > 1 ? $this->pick($allowed) : $this->pick(['deposit', 'collect', 'endorse', 'bounce', 'pay']);
        $data = [
            'type' => $type,
            'occurred_on' => $this->date($instrument->issued_on->toDateString()),
            'bank_id' => $this->pick([$this->cashBox->id, $this->bank->id]),
            'party_id' => $this->pick(array_merge(...array_values($this->parties)))->id,
        ];

        $this->attempt('step', fn () => $this->actingAs($this->admin)->post(route('admin.financial-instruments.movements.store', $instrument), $data));

        return 'çek '.$type;
    }

    private function undoOperation(): string
    {
        $instrument = $this->pickRow(FinancialInstrument::query()->whereHas('movements'));

        if (! $instrument) {
            return 'geri alınacak çek adımı yok';
        }

        $this->attempt('undo', fn () => $this->actingAs($this->admin)->delete(route('admin.financial-instruments.movements.undo', $instrument)));

        return 'çek geri al';
    }

    private function ownerOperation(): string
    {
        $vehicle = $this->pick($this->vehicles)->refresh();
        $current = $vehicle->currentOwnership()->first();

        if ($current?->party?->isTemporary()) {
            $this->attempt('owner', fn () => $this->actingAs($this->admin)->post(route('admin.vehicles.ownerships.reassign', $vehicle), ['party_id' => $this->pick($this->parties['vehicle_owner'])->id]));

            return 'geçici plakayı sahibine aktar';
        }

        if ($vehicle->ownerships()->count() > 1 && mt_rand(0, 1) === 1) {
            $this->attempt('owner', fn () => $this->actingAs($this->admin)->delete(route('admin.vehicles.ownerships.destroy', [$vehicle, $current])));

            return 'devri geri al';
        }

        $this->attempt('owner', fn () => $this->actingAs($this->admin)->post(route('admin.vehicles.ownerships.store', $vehicle), [
            'party_id' => $this->pick([...$this->parties['vehicle_owner'], ...$this->parties['customer']])->id,
            'started_on' => $this->date($current?->started_on->copy()->addDay()->toDateString() ?? '2026-02-01'),
            'settlement' => $this->pick(['carry', 'settled']),
        ]));

        return 'plaka devri';
    }

    private function siteOperation(): string
    {
        $site = $this->pick($this->sites);
        $site->update(['party_id' => $this->pick([null, $this->parties['customer'][0]->id, $this->parties['customer'][1]->id])]);
        $this->done['site'] = ($this->done['site'] ?? 0) + 1;

        return 'şantiye carisini değiştir';
    }

    private function haulOperation(): string
    {
        $this->attempt('haul', fn () => $this->actingAs($this->admin)->post(route('admin.hauls.store'), $this->haulPayload('F-'.mt_rand(10000, 99999))));

        return 'taşıma fişi';
    }

    private function machineOperation(): string
    {
        $this->attempt('machine', fn () => $this->actingAs($this->admin)->post(route('admin.machine-works.store'), $this->machinePayload('M-'.mt_rand(10000, 99999))));

        return 'makine fişi';
    }

    private function fuelOperation(): string
    {
        $this->attempt('fuel', fn () => $this->actingAs($this->admin)->post(route('admin.fuel-receipts.store'), $this->fuelPayload('Y-'.mt_rand(10000, 99999))));

        return 'yakıt fişi';
    }

    private function shipmentOperation(): string
    {
        $this->attempt('shipment', fn () => $this->actingAs($this->admin)->post(route('admin.shipments.store'), $this->shipmentPayload()));

        return 'nakliye seferi';
    }

    private function fillOperation(): string
    {
        $tank = $this->stations[1];
        $fill = $this->pickRow(FuelTankFill::query());

        if ($fill && mt_rand(0, 2) === 0) {
            $this->attempt('fill', fn () => $this->actingAs($this->admin)->delete(route('admin.fuel-stations.fills.destroy', [$tank, $fill])));

            return 'depo dolumu sil';
        }

        $this->attempt('fill', fn () => $this->actingAs($this->admin)->post(route('admin.fuel-stations.fills.store', $tank), [
            'party_id' => $this->pick($this->parties['fuel'])->id,
            'filled_on' => $this->date('2026-02-01'),
            'quantity' => (string) mt_rand(100, 2000),
            'unit_price' => '41.5',
        ]));

        return 'depo dolumu';
    }

    private function payrollOperation(): string
    {
        $employee = $this->pick($this->employees);
        $month = mt_rand(1, 9);

        return match (mt_rand(0, 4)) {
            0 => tap('maaş', fn () => $this->attempt('payroll', fn () => $this->actingAs($this->admin)->post(route('admin.employee-salaries.store'), [
                'year' => 2026,
                'month' => $month,
                'rows' => [['employee_id' => $employee->id, 'base_salary' => (string) mt_rand(20000, 40000), 'worked_days' => (string) mt_rand(0, 30)]],
            ]))),
            1 => tap('prim', fn () => $this->attempt('payroll', fn () => $this->actingAs($this->admin)->post(route('admin.employee-bonuses.store'), [
                'employee_id' => $employee->id,
                'amount' => (string) mt_rand(100, 3000),
                'occurred_on' => $this->date('2026-02-01'),
            ]))),
            2 => tap('personel ödemesi', fn () => $this->attempt('payroll', fn () => $this->actingAs($this->admin)->post(route('admin.employee-payments.store'), [
                'employee_id' => $employee->id,
                'amount' => (string) mt_rand(100, 9000),
                'payment_method' => $method = $this->pick(['cash', 'bank_transfer', 'offset']),
                'bank_id' => $method === 'cash' ? $this->cashBox->id : ($method === 'bank_transfer' ? $this->bank->id : null),
                'year' => 2026,
                'month' => $month,
                'occurred_on' => $this->date('2026-02-01'),
            ]))),
            3 => tap('prim sil', function (): void {
                if ($bonus = $this->pickRow(EmployeeBonus::query())) {
                    $this->attempt('payroll', fn () => $this->actingAs($this->admin)->delete(route('admin.employee-bonuses.destroy', $bonus)));
                }
            }),
            default => tap('personel ödemesi sil', function (): void {
                if ($payment = $this->pickRow(EmployeePayment::query())) {
                    $this->attempt('payroll', fn () => $this->actingAs($this->admin)->delete(route('admin.employee-payments.destroy', $payment)));
                }
            }),
        };
    }

    /**
     * Edits or deletes a random work document: the edits that move a document across dates,
     * dump sites and owners are where postings could go astray.
     */
    private function documentOperation(): string
    {
        $edit = mt_rand(0, 2) > 0;

        switch (mt_rand(0, 3)) {
            case 0:
                if (! $haul = $this->pickRow(Haul::query())) {
                    return 'taşıma yok';
                }

                $this->attempt('document', fn () => $edit
                    ? $this->actingAs($this->admin)->put(route('admin.hauls.update', $haul), $this->haulPayload($haul->ticket_no))
                    : $this->actingAs($this->admin)->delete(route('admin.hauls.destroy', $haul)));

                return $edit ? 'taşıma düzenle' : 'taşıma sil';
            case 1:
                if (! $work = $this->pickRow(MachineWork::query())) {
                    return 'makine fişi yok';
                }

                $this->attempt('document', fn () => $edit
                    ? $this->actingAs($this->admin)->put(route('admin.machine-works.update', $work), $this->machinePayload($work->ticket_no))
                    : $this->actingAs($this->admin)->delete(route('admin.machine-works.destroy', $work)));

                return $edit ? 'makine düzenle' : 'makine sil';
            case 2:
                if (! $receipt = $this->pickRow(FuelReceipt::query())) {
                    return 'yakıt fişi yok';
                }

                $this->attempt('document', fn () => $edit
                    ? $this->actingAs($this->admin)->put(route('admin.fuel-receipts.update', $receipt), $this->fuelPayload($receipt->ticket_no))
                    : $this->actingAs($this->admin)->delete(route('admin.fuel-receipts.destroy', $receipt)));

                return $edit ? 'yakıt düzenle' : 'yakıt sil';
            default:
                if (! $shipment = $this->pickRow(Shipment::query())) {
                    return 'sefer yok';
                }

                $this->attempt('document', fn () => $edit
                    ? $this->actingAs($this->admin)->put(route('admin.shipments.update', $shipment), $this->shipmentPayload())
                    : $this->actingAs($this->admin)->delete(route('admin.shipments.destroy', $shipment)));

                return $edit ? 'sefer düzenle' : 'sefer sil';
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function haulPayload(?string $ticketNo): array
    {
        return [
            'ticket_no' => $ticketNo,
            'plate' => $this->pick(array_slice($this->vehicles, 0, 3))->plate,
            'trips' => mt_rand(1, 3),
            'hauled_at' => $this->date('2026-02-01').'T10:00',
            'site_id' => $this->route->site_id,
            'dump_site_id' => $this->route->dump_site_id,
            'tonnage' => 18,
            'shift' => $this->pick(['day', 'night']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function machinePayload(?string $ticketNo): array
    {
        $operator = mt_rand(0, 1) === 1;

        return [
            'ticket_no' => $ticketNo,
            'plate' => $this->vehicles[3]->plate,
            'worked_on' => $this->date('2026-02-01'),
            'shift' => 'day',
            'site_id' => $this->pick($this->sites)->id,
            'operator_id' => $operator ? $this->operator->id : '',
            'operator_rate' => $operator ? (string) mt_rand(50, 300) : '',
            'hours' => (string) mt_rand(1, 10),
            'hourly_rate' => '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fuelPayload(?string $ticketNo): array
    {
        return [
            'ticket_no' => $ticketNo,
            'plate' => $this->pick($this->vehicles)->plate,
            'issued_on' => $this->date('2026-02-01'),
            'shift' => 'day',
            'fuel_station_id' => $this->pick($this->stations)->id,
            'site_id' => $this->pick([$this->sites[0]->id, '']),
            'quantity' => (string) mt_rand(10, 300),
            'unit_price' => '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function shipmentPayload(): array
    {
        $commission = mt_rand(0, 2) === 0;

        return [
            'plate' => $this->pick(array_slice($this->vehicles, 0, 3))->plate,
            'waybill_no' => 'IRS-'.mt_rand(1000, 9999),
            'carrier_price' => (string) mt_rand(100, 400),
            'carrier_vat_rate' => $this->pick(['0', '20']),
            'carrier_withholding_rate' => $this->pick(['0.00', '0.20']),
            'driver_name' => 'Şoför',
            'driver_amount' => (string) mt_rand(0, 2000),
            'shipped_on' => $this->date('2026-02-01'),
            'net_kg' => (string) mt_rand(1000, 40000),
            'loading_place' => 'Ocak',
            'customer_party_id' => $this->pick($this->parties['customer'])->id,
            'sale_price' => (string) mt_rand(300, 800),
            'vat_rate' => '20',
            'withholding_rate' => '0.20',
            'commission_party_id' => $commission ? $this->parties['supplier'][0]->id : '',
            'commission_amount' => $commission ? (string) mt_rand(100, 900) : '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(CariEntryKind $kind): array
    {
        $methods = ['cash', 'bank_transfer', 'cheque', 'cheque', 'promissory_note', 'promissory_note', 'offset'];
        $method = $this->pick($kind->requiresPaymentMethod() ? $methods : [...$methods, null]);
        $occurredOn = $this->date('2026-02-01');
        $plate = $this->pick($this->vehicles)->plate;

        return array_filter([
            'kind' => $kind->value,
            'direction' => $this->pick(['debit', 'credit']),
            'amount' => (string) (mt_rand(1, 2000) * 5),
            'occurred_on' => $occurredOn,
            'payment_method' => $method,
            'bank_id' => match (true) {
                $kind === CariEntryKind::AccountTransfer => $this->pick([$this->cashBox->id, $this->bank->id]),
                $method === 'cash' => $this->cashBox->id,
                $method === 'bank_transfer' => $this->bank->id,
                default => null,
            },
            'counter_bank_id' => $kind === CariEntryKind::AccountTransfer ? $this->pick([$this->cashBox->id, $this->bank->id]) : null,
            'due_on' => in_array($method, ['cheque', 'promissory_note'], true) ? Carbon::parse($occurredOn)->addDays(mt_rand(0, 90))->toDateString() : null,
            'cheque_no' => in_array($method, ['cheque', 'promissory_note'], true) ? 'FZ'.mt_rand(1000, 9999) : null,
            'party_id' => match ($kind) {
                CariEntryKind::Supplier, CariEntryKind::Customer, CariEntryKind::Fuel, CariEntryKind::Market, CariEntryKind::OwnerPayment => $this->pick($this->parties[$kind->partyRole()->value])->id,
                CariEntryKind::Opening, CariEntryKind::PartyTransfer => $this->pick(array_merge(...array_values($this->parties)))->id,
                default => null,
            },
            'counter_party_id' => $kind === CariEntryKind::PartyTransfer ? $this->pick(array_merge(...array_values($this->parties)))->id : null,
            'plate' => in_array($kind, [CariEntryKind::VehiclePayment, CariEntryKind::VehicleReceivable, CariEntryKind::Market], true) ? $plate : null,
            'receiver_name' => $kind === CariEntryKind::VehiclePayment ? 'Teslim Alan' : null,
            'receiver_national_id' => $kind === CariEntryKind::VehiclePayment ? '12345678901' : null,
            'site_id' => in_array($kind, [CariEntryKind::Site, CariEntryKind::SiteBill], true) ? $this->pick($this->sites)->id : null,
            'dump_site_id' => $kind === CariEntryKind::DumpPayment ? $this->dumpSite->id : null,
            'description' => $this->pick(['', 'fuzz']),
        ], fn ($value) => $value !== null);
    }

    /**
     * @return array{0: CariEntryKind, 1: string}|null
     */
    private function existingEntry(): ?array
    {
        $kind = $this->pick(CariEntryKind::cases());

        $query = match ($kind) {
            CariEntryKind::Supplier, CariEntryKind::Customer, CariEntryKind::Fuel, CariEntryKind::Market => CompanyTransaction::query()->where('role', $kind->value),
            CariEntryKind::VehiclePayment => VehiclePayment::query(),
            CariEntryKind::VehicleReceivable => VehicleReceivable::query(),
            CariEntryKind::OwnerPayment => PartyPayment::query(),
            CariEntryKind::DumpPayment => DumpSitePayment::query(),
            CariEntryKind::Site => SiteTransaction::query(),
            CariEntryKind::Opening => OpeningBalance::query(),
            CariEntryKind::PartyTransfer => PartyTransfer::query(),
            CariEntryKind::SiteBill => SiteBill::query(),
            CariEntryKind::AccountTransfer => AccountTransfer::query(),
            CariEntryKind::Cash => null,
        };

        $id = $query ? $this->pickRow($query)?->id : null;

        return $id ? [$kind, $id] : null;
    }

    /**
     * Runs the request; a validation error or a refusal is a valid outcome, a server error is not.
     */
    private function attempt(string $operation, callable $request): void
    {
        $response = $request();
        $this->assertLessThan(500, $response->getStatusCode(), "{$operation}: sunucu hatası");

        if ($response->isRedirect() && session('success')) {
            $this->done[$operation] = ($this->done[$operation] ?? 0) + 1;
        }

        $this->flushSession();
    }

    private function assertNoLeak(string $context): void
    {
        foreach (app(LedgerAudit::class)->run() as $check) {
            $this->assertSame([], $check['failures'], "{$context}: {$check['check']}");
        }

        $plateless = DB::table('ledger_entries as le')
            ->where('le.party_id', $this->temporary->id)
            ->whereNotIn('le.source_type', ['haul', 'machine_work', 'fuel_receipt', 'shipment', 'vehicle_payment', 'vehicle_receivable', 'vehicle_ownership', 'financial_instrument_movement'])
            ->count();
        $this->assertSame(0, $plateless, "{$context}: Geçici Cari'de plakaya bağlı olmayan satır");
    }

    private function date(string $from): string
    {
        $start = Carbon::parse($from)->timestamp;
        $end = max($start, Carbon::parse('2026-10-28')->timestamp);

        return Carbon::createFromTimestamp(mt_rand($start, $end))->toDateString();
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return TModel|null
     */
    private function pickRow($query): mixed
    {
        $rows = $query->orderBy('id')->get();

        return $rows->isEmpty() ? null : $rows[mt_rand(0, $rows->count() - 1)];
    }

    /**
     * @template T
     *
     * @param  list<T>  $items
     * @return T
     */
    private function pick(array $items): mixed
    {
        return $items[mt_rand(0, count($items) - 1)];
    }
}
