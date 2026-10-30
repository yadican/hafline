<?php

namespace Database\Seeders\LoadTest;

use App\Enums\AccountType;
use App\Enums\CashDirection;
use App\Enums\InvoiceDirection;
use App\Enums\InvoicePaymentStatus;
use App\Enums\InvoiceRecipient;
use App\Enums\PartyRole;
use App\Enums\PaymentMethod;
use App\Enums\SiteBilling;
use App\Exceptions\OperationException;
use App\Exceptions\OperationRejectedException;
use App\Models\Bank;
use App\Models\CashCategory;
use App\Models\CashEntry;
use App\Models\Employee;
use App\Models\FuelReceipt;
use App\Models\FuelStation;
use App\Models\Haul;
use App\Models\HaulRoute;
use App\Models\Invoice;
use App\Models\LedgerEntry;
use App\Models\MachineWork;
use App\Models\Party;
use App\Models\Site;
use App\Models\SiteTransaction;
use App\Models\StockItem;
use App\Models\User;
use App\Models\Vehicle;
use App\Repositories\EmployeeSalaryRepository;
use App\Repositories\Reports\DumpSitePaymentReportRepository;
use App\Repositories\VehicleAccountRepository;
use App\Services\CariEntryService;
use App\Services\EmployeeBonusService;
use App\Services\EmployeePaymentService;
use App\Services\FuelReceiptService;
use App\Services\HaulService;
use App\Services\InvoiceService;
use App\Services\MachineWorkService;
use App\Services\Reports\CashMovementReportService;
use App\Services\StockCountService;
use App\Services\StockService;
use App\Services\StockUsageService;
use App\Services\VehicleOwnershipService;
use Database\Seeders\LoadTest\Support\TurkishData;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * One working day of the business, entered through the same services as the admin screens.
 *
 * Everything is derived from what is already in the database: trucks keep yesterday's route,
 * trip counts follow each route's recent history, trucks and machines refuel when their tank is
 * spent, plates are paid on their weekly pay day from their open balance, dump sites on the 1st
 * and 16th, fuel firms are paid from receipt open balance on Wednesdays, sites are billed at
 * month end, staff are paid early in the month and the cash drawer is topped up from the bank
 * via account transfer. Purchases, customer/plate sales and stock exits go through InvoiceService
 * / stock services (no fake supplier "fatura" cari). Times that have not come yet today are not
 * written.
 */
final class DailyStage
{
    public const USERNAME = 'simulasyon';

    /** Idle chance (%) by day type: weekday, Saturday, Sunday. */
    private const HAUL_IDLE = [8, 25, 65];

    private const MACHINE_IDLE = [15, 35, 70];

    private const SHIFT_MINUTES = [[600, 720], [480, 600], [300, 420]];

    private const MACHINE_HOURS = [[7, 8, 8, 9, 9, 9.5, 10, 10.5, 11], [5, 6, 7, 8, 8], [4, 5, 6]];

    private const LITRES_PER_TRIP = ['TIR' => 19, 'KIRKAYAK' => 16, 'ON TEKER' => 13, 'TAKSİ' => 7];

    private const TANK_LITRES = ['TIR' => 400, 'KIRKAYAK' => 350, 'ON TEKER' => 300, 'TAKSİ' => 160, 'MAKİNE' => 400];

    private const MACHINE_LITRES_PER_HOUR = 14;

    private const PLATE_METHODS = [PaymentMethod::BankTransfer, PaymentMethod::BankTransfer, PaymentMethod::Cash, PaymentMethod::Cheque, PaymentMethod::Offset, PaymentMethod::PromissoryNote];

    private Carbon $day;

    private Carbon $cutoff;

    private User $user;

    private int $dayType;

    private int $ticket = 0;

    /** @var array<string, int> */
    private array $counts = [];

    /** @var list<string> */
    private array $skipped = [];

    public function __construct(
        private HaulService $hauls,
        private FuelReceiptService $receipts,
        private MachineWorkService $works,
        private CariEntryService $entries,
        private InvoiceService $invoices,
        private StockService $stock,
        private StockUsageService $usages,
        private StockCountService $stockCounts,
        private EmployeePaymentService $employeePayments,
        private EmployeeBonusService $bonuses,
        private EmployeeSalaryRepository $salaries,
        private VehicleOwnershipService $ownerships,
        private VehicleAccountRepository $accounts,
        private DumpSitePaymentReportRepository $dumpTotals,
        private CashMovementReportService $cash,
    ) {}

    public static function user(): User
    {
        return User::query()->firstOrCreate(['username' => self::USERNAME], [
            'name' => 'Simülasyon',
            'email' => 'simulasyon@hafline.local',
            'password' => Hash::make(Str::random(40)),
            'active' => false,
        ]);
    }

    public static function alreadyRan(Carbon $day): bool
    {
        $userId = self::user()->id;
        $date = $day->toDateString();

        return LedgerEntry::query()->where('created_by', $userId)->whereDate('occurred_on', $date)->exists()
            || CashEntry::query()->withTrashed()->where('created_by', $userId)->whereDate('occurred_on', $date)->exists()
            || SiteTransaction::query()->withTrashed()->where('created_by', $userId)->whereDate('occurred_on', $date)->exists();
    }

    /**
     * @return array{counts: array<string, int>, skipped: list<string>}
     */
    public function run(Carbon $day): array
    {
        $this->day = $day->copy()->startOfDay();
        $this->cutoff = $this->day->isToday() ? now() : $this->day->copy()->endOfDay();
        $this->dayType = match ($this->day->dayOfWeek) {
            Carbon::SATURDAY => 1,
            Carbon::SUNDAY => 2,
            default => 0,
        };
        $this->user = self::user();
        Auth::setUser($this->user);

        $this->transfer();
        $this->truckDay();
        $this->machineDay();
        $this->corrections();
        $this->plateSettlements();
        $this->dumpSettlements();
        $this->fuelBilling();
        $this->siteBilling();
        $this->invoicesAndStock();
        $this->market();
        $this->siteMovements();
        $this->operatorSettlements();
        $this->payroll();
        $this->cashDrawer();

        return ['counts' => $this->counts, 'skipped' => $this->skipped];
    }

    private function truckDay(): void
    {
        $routes = HaulRoute::query()->where('active', true)->get();

        if ($routes->isEmpty()) {
            return;
        }

        $tripsPerDay = $this->routeTripsPerDay();
        $trucks = Vehicle::query()
            ->where('active', true)
            ->whereHas('vehicleClass', fn ($query) => $query->where('hourly', false))
            ->with('vehicleClass')
            ->get();

        foreach ($trucks as $truck) {
            if (! $truck->ownershipOn($this->day) || fake()->boolean(self::HAUL_IDLE[$this->dayType])) {
                continue;
            }

            $last = Haul::query()->where('vehicle_id', $truck->id)->where('hauled_at', '<', $this->day)->latest('hauled_at')->first();
            $route = $this->nextRoute($last ? $routes->firstWhere('id', $last->route_id) : null, $routes);
            $trips = max(1, (int) round(($tripsPerDay[$route->id] ?? 6) * fake()->randomFloat(2, 0.8, 1.15) * [1, 0.8, 0.5][$this->dayType]));
            $start = $this->day->copy()->setTime(8, 15)->addMinutes(fake()->numberBetween(0, 45));
            $gap = intdiv(fake()->numberBetween(...self::SHIFT_MINUTES[$this->dayType]), $trips);
            $tonnage = $last ? max(8, $last->tonnage + fake()->numberBetween(-2, 2)) : fake()->numberBetween(14, 28);

            $this->refuelTruck($truck, $route, $start->copy()->subMinutes(fake()->numberBetween(5, 15)));

            for ($trip = 0; $trip < $trips; $trip++) {
                $hauledAt = $start->copy()->addMinutes($trip * $gap + fake()->numberBetween(0, 12));

                if ($hauledAt->gt($this->cutoff)) {
                    break;
                }

                $this->attempt('sefer', fn () => $this->hauls->create([
                    'ticket_no' => $this->ticketNo('S'),
                    'plate' => $truck->plate,
                    'trips' => 1,
                    'hauled_at' => $hauledAt->format('Y-m-d H:i'),
                    'site_id' => $route->site_id,
                    'dump_site_id' => $route->dump_site_id,
                    'tonnage' => $tonnage,
                    'shift' => 'day',
                ], $this->user->id));
            }
        }
    }

    /**
     * Fills up in the morning once the trips since the last receipt have spent most of the tank.
     */
    private function refuelTruck(Vehicle $truck, HaulRoute $route, Carbon $at): void
    {
        $class = $truck->vehicleClass?->name;
        $last = FuelReceipt::query()->where('vehicle_id', $truck->id)->where('issued_on', '<', $this->day->toDateString())->latest('issued_on')->first();
        $trips = Haul::query()
            ->where('vehicle_id', $truck->id)
            ->when($last, fn ($query) => $query->where('hauled_at', '>=', $last->issued_on->copy()->startOfDay()))
            ->where('hauled_at', '<', $this->day)
            ->count();
        $litres = $trips * (self::LITRES_PER_TRIP[$class] ?? 13);
        $tank = self::TANK_LITRES[$class] ?? 300;

        if ($at->gt($this->cutoff) || $litres < $tank * 0.6) {
            return;
        }

        $station = $last?->fuelStation?->active ? $last->fuelStation : $this->stationFor($route->site_id);

        if ($station) {
            $this->fuel($truck, $station, $route->site_id, min($tank, $litres), $trips, $at, $truck->driver_name);
        }
    }

    private function machineDay(): void
    {
        $machines = Vehicle::query()
            ->where('active', true)
            ->whereHas('vehicleClass', fn ($query) => $query->where('hourly', true))
            ->get();
        $sites = DB::table('sites')->whereNull('deleted_at')->where('active', true)->pluck('id');

        foreach ($machines as $machine) {
            if (! $machine->ownershipOn($this->day) || ! $machine->rateOn($this->day) || fake()->boolean(self::MACHINE_IDLE[$this->dayType])) {
                continue;
            }

            $hours = (float) fake()->randomElement(self::MACHINE_HOURS[$this->dayType]);

            if ($this->day->copy()->setTime(8, 0)->addMinutes((int) round($hours * 60))->gt($this->cutoff)) {
                continue;
            }

            $last = MachineWork::query()->where('vehicle_id', $machine->id)->where('worked_on', '<', $this->day->toDateString())->latest('worked_on')->first();
            $siteId = $last && ! fake()->boolean(6) ? $last->site_id : $sites->random();
            $operator = $last?->operator_id ? Party::query()->find($last->operator_id) : null;

            $this->refuelMachine($machine, $siteId);

            $this->attempt('makine fişi', fn () => $this->works->create([
                'ticket_no' => $this->ticketNo('M'),
                'plate' => $machine->plate,
                'worked_on' => $this->day->toDateString(),
                'shift' => fake()->boolean(85) ? 'day' : 'night',
                'site_id' => $siteId,
                'operator_id' => $operator?->hasRole(PartyRole::MachineOperator) ? $operator->id : null,
                'operator_rate' => $last?->operator_rate,
                'hours' => $hours,
                'hourly_rate' => null,
            ], $this->user->id));
        }
    }

    private function refuelMachine(Vehicle $machine, string $siteId): void
    {
        $at = $this->day->copy()->setTime(fake()->numberBetween(8, 9), fake()->numberBetween(0, 59));
        $last = FuelReceipt::query()->where('vehicle_id', $machine->id)->where('issued_on', '<', $this->day->toDateString())->latest('issued_on')->first();
        $hours = (float) MachineWork::query()
            ->where('vehicle_id', $machine->id)
            ->when($last, fn ($query) => $query->where('worked_on', '>=', $last->issued_on->toDateString()))
            ->where('worked_on', '<', $this->day->toDateString())
            ->sum('hours');
        $litres = $hours * self::MACHINE_LITRES_PER_HOUR;

        if ($at->gt($this->cutoff) || $litres < self::TANK_LITRES['MAKİNE'] * 0.55) {
            return;
        }

        if ($station = $this->stationFor($siteId)) {
            $this->fuel($machine, $station, $siteId, min(self::TANK_LITRES['MAKİNE'], $litres), 0, $at, TurkishData::personName());
        }
    }

    private function fuel(Vehicle $vehicle, FuelStation $station, string $siteId, float $litres, int $tickets, Carbon $at, ?string $receivedBy): void
    {
        $this->attempt('yakıt fişi', fn () => $this->receipts->create([
            'ticket_no' => $this->ticketNo('Y'),
            'plate' => $vehicle->plate,
            'issued_on' => $at->toDateString(),
            'shift' => 'day',
            'fuel_station_id' => $station->id,
            'site_id' => $siteId,
            'quantity' => (string) round($litres),
            'unit_price' => null,
            // Station purchase_price credits the fuel firm (40a); tank fills stay at 0 via the service.
            'purchase_price' => $station->isTank() ? '0' : (string) $station->purchase_price,
            'received_by' => $receivedBy ?? TurkishData::personName(),
            'haul_ticket_count' => $tickets,
        ], $this->user->id));
    }

    /**
     * Data entry slips: a few of today's tickets get their tonnage fixed, the odd one is cancelled.
     */
    private function corrections(): void
    {
        $today = Haul::query()->where('created_by', $this->user->id)->whereDate('hauled_at', $this->day->toDateString())->get();

        foreach ($today as $haul) {
            if (fake()->boolean(3)) {
                $this->attempt('sefer düzeltmesi', fn () => $this->hauls->update($haul, [
                    'ticket_no' => $haul->ticket_no,
                    'plate' => $haul->vehicle->plate,
                    'hauled_at' => $haul->hauled_at->format('Y-m-d H:i'),
                    'site_id' => $haul->site_id,
                    'dump_site_id' => $haul->dump_site_id,
                    'tonnage' => max(8, $haul->tonnage + fake()->randomElement([-2, -1, 1, 2])),
                    'shift' => $haul->shift->value,
                ]));
            } elseif (fake()->randomFloat(2, 0, 100) < 0.5) {
                $this->attempt('sefer iptali', fn () => $this->hauls->delete($haul));
            }
        }
    }

    /**
     * Now and then a truck is sold from today on; the seller's plate balance goes to the buyer.
     */
    private function transfer(): void
    {
        if (! fake()->boolean(4)) {
            return;
        }

        $truck = Vehicle::query()
            ->where('active', true)
            ->whereHas('ownerships', fn ($query) => $query->whereNull('ended_on')->where('started_on', '<', $this->day->toDateString()))
            ->inRandomOrder()
            ->first();
        $buyer = $truck ? Party::query()->withRole(PartyRole::VehicleOwner)->where('id', '<>', $truck->currentOwnership->party_id)->inRandomOrder()->first() : null;

        if ($truck && $buyer) {
            $this->attempt('araç devri', fn () => $this->ownerships->transfer($truck, $buyer, $this->day->copy(), true));
        }
    }

    /**
     * Each plate is paid on its weekly pay day: 70–90% of what the company owes on it.
     */
    private function plateSettlements(): void
    {
        $payWeekdays = [Carbon::MONDAY, Carbon::FRIDAY, Carbon::SATURDAY];

        foreach (Vehicle::query()->where('active', true)->get() as $vehicle) {
            if ($payWeekdays[crc32($vehicle->plate) % 3] !== $this->day->dayOfWeek) {
                continue;
            }

            $owner = $vehicle->ownershipOn($this->day);

            if (! $owner) {
                continue;
            }

            if (fake()->boolean(5)) {
                $this->cari('plaka alacak', [
                    'kind' => 'vehicle_receivable',
                    'plate' => $vehicle->plate,
                    'amount' => $this->roundDown(fake()->numberBetween(300, 2_500)),
                    'payment_method' => PaymentMethod::Offset->value,
                    'description' => fake()->randomElement(['Sefer farkı', 'Kesinti iadesi', 'Bekleme ücreti']),
                ]);
            }

            $open = $this->accounts->balance($vehicle->id, $owner->party_id);

            if ($open >= 1_500) {
                $this->cari('plaka ödeme', [
                    'kind' => 'vehicle_payment',
                    'plate' => $vehicle->plate,
                    'amount' => $this->roundDown($open * fake()->randomFloat(2, 0.7, 0.9)),
                    'payment_method' => fake()->randomElement(self::PLATE_METHODS)->value,
                    'description' => fake()->randomElement(['Haftalık hakediş', 'Sefer mahsubu', 'Plaka ödemesi']),
                ]);
            }
        }
    }

    private function dumpSettlements(): void
    {
        if (! in_array($this->day->day, [1, 16], true)) {
            return;
        }

        foreach (DB::table('dump_sites')->whereNull('deleted_at')->whereNotNull('party_id')->pluck('id') as $dumpSiteId) {
            $open = $this->dumpTotals->closingTotals($dumpSiteId, $this->day->copy()->addDay())['net'];

            if ($open >= 5_000) {
                $method = fake()->boolean(60) ? PaymentMethod::BankTransfer : PaymentMethod::Cheque;
                $this->cari('döküm ödemesi', [
                    'kind' => 'dump_payment',
                    'dump_site_id' => $dumpSiteId,
                    'amount' => $this->roundDown($open * fake()->randomFloat(2, 0.8, 0.95)),
                    'payment_method' => $method->value,
                    'cheque_no' => $method === PaymentMethod::Cheque ? (string) fake()->numberBetween(100_000, 999_999) : null,
                    'description' => 'Döküm ödemesi',
                ]);
            }
        }
    }

    /**
     * Receipts already credit the fuel firm at purchase_price on the ledger; Wednesdays pay
     * down that open balance (no separate half-month invoice).
     */
    private function fuelBilling(): void
    {
        if ($this->day->dayOfWeek !== Carbon::WEDNESDAY) {
            return;
        }

        foreach (Party::query()->withRole(PartyRole::Fuel)->get() as $party) {
            $open = round((float) DB::table('ledger_entries')
                ->where('party_id', $party->id)
                ->whereNull('reversal_of')
                ->where('occurred_on', '<=', $this->day->toDateString())
                ->sum(DB::raw('credit - debit')), 2);

            if ($open >= 1_000 && fake()->boolean(70)) {
                $this->cari('akaryakıt ödemesi', [
                    'kind' => PartyRole::Fuel->value,
                    'direction' => 'debit',
                    'party_id' => $party->id,
                    'amount' => $this->roundDown($open * fake()->randomFloat(2, 0.6, 0.9)),
                    'payment_method' => fake()->randomElement([
                        PaymentMethod::BankTransfer,
                        PaymentMethod::BankTransfer,
                        PaymentMethod::Cheque,
                    ])->value,
                    'cash_category_id' => $this->category(CashDirection::Expense)?->id,
                    'document_no' => $this->ticketNo('OD'),
                    'description' => 'Akaryakıt ödemesi',
                ]);
            }
        }
    }

    /**
     * Progress sites get a month-end hakediş on their own cari; both billing modes pay down open
     * site borç with şantiye tahsilatı (Alıcı Cari hakediş is gone).
     */
    private function siteBilling(): void
    {
        $sites = Site::query()->where('active', true)->whereNotNull('party_id')->get();

        if ($this->day->isLastOfMonth()) {
            $from = $this->day->copy()->startOfMonth();

            foreach ($sites->filter(fn (Site $site) => $site->billing_mode === SiteBilling::Progress) as $site) {
                $revenue = (float) Haul::query()->where('site_id', $site->id)->whereBetween('hauled_at', [$from, $this->day->copy()->endOfDay()])->sum('site_price')
                    + (float) MachineWork::query()->where('site_id', $site->id)->whereBetween('worked_on', [$from->toDateString(), $this->day->toDateString()])->sum('amount');

                if ($revenue >= 10_000) {
                    $this->cari('hakediş', [
                        'kind' => 'site_bill',
                        'site_id' => $site->id,
                        'amount' => $this->roundDown($revenue * fake()->randomFloat(2, 1.18, 1.32)),
                        'description' => $this->day->translatedFormat('F Y').' hakedişi',
                    ]);
                }
            }
        }

        foreach ($sites as $site) {
            $open = $this->sitePartyBalance($site->party_id);

            if ($open >= 10_000 && fake()->boolean(6)) {
                $method = fake()->randomElement([
                    PaymentMethod::BankTransfer,
                    PaymentMethod::BankTransfer,
                    PaymentMethod::Cheque,
                    PaymentMethod::PromissoryNote,
                ]);
                $this->cari('şantiye tahsilatı', [
                    'kind' => 'site',
                    'direction' => 'credit',
                    'site_id' => $site->id,
                    'amount' => $this->roundDown($open * fake()->randomFloat(2, 0.3, 0.7)),
                    'payment_method' => $method->value,
                    'cash_category_id' => $this->category(CashDirection::Income)?->id,
                    'description' => 'Şantiye tahsilatı',
                ]);
            }
        }
    }

    /**
     * Debit − credit on the site's cari up to today (positive: the site owes us).
     */
    private function sitePartyBalance(string $partyId): float
    {
        return round((float) LedgerEntry::query()->toBase()
            ->where('party_id', $partyId)
            ->whereNull('reversal_of')
            ->where('occurred_on', '<=', $this->day->toDateString())
            ->sum(DB::raw('debit - credit')), 2);
    }

    /**
     * Real purchase/sale invoices and stock exits for the day (G·45 rules at daily scale).
     * Supplier "fatura" cari rows are gone — InvoiceService posts the cari line (K5).
     */
    private function invoicesAndStock(): void
    {
        $tracked = StockItem::query()->where('active', true)->where('tracks_stock', true)->get();

        if ($tracked->isEmpty()) {
            return;
        }

        $service = StockItem::query()->where('active', true)->where('tracks_stock', false)->first();
        $expense = $this->category(CashDirection::Expense);
        $income = $this->category(CashDirection::Income);
        $sites = Site::query()->where('active', true)->get();
        $suppliers = Party::query()->withRole(PartyRole::Supplier)->where('active', true)->get();
        $customers = Party::query()->withRole(PartyRole::Customer)->where('active', true)->get();
        $vehicles = Vehicle::query()
            ->where('active', true)
            ->whereHas('vehicleClass', fn ($query) => $query->where('hourly', false))
            ->get();

        foreach ($suppliers as $supplier) {
            if (fake()->boolean(7)) {
                $this->writePurchase($supplier, $tracked, $service, $expense, $sites);
            }
        }

        if ($customers->isNotEmpty() && fake()->boolean(10)) {
            $this->writeCustomerSale($customers, $tracked, $service, $income);
        }

        if ($vehicles->isNotEmpty() && fake()->boolean(8)) {
            $this->writePlateSale($vehicles, $tracked, $income);
        }

        if ($expense !== null && fake()->boolean(12)) {
            $this->writeUsage($tracked, $sites, $vehicles, $expense->id);
        }

        if (fake()->boolean(4)) {
            $this->writeCount($tracked);
        }

        if ($this->day->dayOfWeek === Carbon::TUESDAY) {
            $this->payOpenInvoices(InvoiceDirection::Purchase, 50, [0.7, 1.0], 'Tedarikçi fatura ödemesi', [
                PaymentMethod::BankTransfer,
                PaymentMethod::BankTransfer,
                PaymentMethod::Cash,
                PaymentMethod::CreditCard,
            ]);
        }

        if ($this->day->day === 10) {
            $this->payOpenInvoices(InvoiceDirection::Sale, 70, [0.7, 1.0], 'Müşteri fatura tahsilatı', [
                PaymentMethod::BankTransfer,
                PaymentMethod::Cash,
            ]);
        }
    }

    /**
     * @param  Collection<int, StockItem>  $tracked
     * @param  Collection<int, Site>  $sites
     */
    private function writePurchase(
        Party $supplier,
        Collection $tracked,
        ?StockItem $service,
        ?CashCategory $expense,
        Collection $sites,
    ): void {
        $item = $tracked->random();
        $qty = $this->purchaseQty($item);
        $unitCost = $this->purchaseUnitCost($item);
        $lines = [[
            'description' => $item->name,
            'stock_item_id' => $item->id,
            'quantity' => $this->qty($qty),
            'unit_price' => $this->money($unitCost),
            'vat_rate' => '0',
            'cash_category_id' => $item->cash_category_id ?? $expense?->id,
        ]];

        if ($service !== null && fake()->boolean(20)) {
            $lines[] = [
                'description' => $service->name,
                'stock_item_id' => $service->id,
                'quantity' => '1',
                'unit_price' => $this->money((float) $service->sales_price * fake()->randomFloat(2, 0.7, 1.0)),
                'vat_rate' => '0',
                'cash_category_id' => $service->cash_category_id ?? $expense?->id,
            ];
        }

        $payload = [
            'direction' => InvoiceDirection::Purchase->value,
            'party_id' => $supplier->id,
            'invoice_no' => $this->ticketNo('A'),
            'issued_on' => $this->day->toDateString(),
            'site_id' => $sites->isNotEmpty() && fake()->boolean(70) ? $sites->random()->id : null,
            'description' => $item->name,
            'lines' => $lines,
            ...$this->entryPayment(InvoiceDirection::Purchase),
        ];

        $this->attempt('alış faturası', fn () => $this->invoices->create($payload, $this->user->id));
    }

    /**
     * @param  Collection<int, Party>  $customers
     * @param  Collection<int, StockItem>  $tracked
     */
    private function writeCustomerSale(
        Collection $customers,
        Collection $tracked,
        ?StockItem $service,
        ?CashCategory $income,
    ): void {
        $item = $tracked->random();
        $available = $this->exitAvailable($item);

        if ($available <= 0) {
            return;
        }

        $qty = $this->exitQty($item, $available);
        $unitPrice = max(
            (float) $item->sales_price,
            $this->stock->stock($item, $this->day)['average_cost'] * fake()->randomFloat(2, 1.05, 1.4),
        );

        $lines = [[
            'description' => $item->name,
            'stock_item_id' => $item->id,
            'quantity' => $this->qty($qty),
            'unit_price' => $this->money($unitPrice),
            'vat_rate' => '0',
            'cash_category_id' => $income?->id,
        ]];

        if ($service !== null && fake()->boolean(15)) {
            $lines[] = [
                'description' => $service->name,
                'stock_item_id' => $service->id,
                'quantity' => '1',
                'unit_price' => $this->money((float) $service->sales_price),
                'vat_rate' => '0',
                'cash_category_id' => $income?->id,
            ];
        }

        $this->attempt('satış faturası', fn () => $this->invoices->create([
            'direction' => InvoiceDirection::Sale->value,
            'recipient' => InvoiceRecipient::Party->value,
            'party_id' => $customers->random()->id,
            'invoice_no' => $this->ticketNo('S'),
            'issued_on' => $this->day->toDateString(),
            'description' => $item->name,
            'lines' => $lines,
            ...$this->entryPayment(InvoiceDirection::Sale),
        ], $this->user->id));
    }

    /**
     * @param  Collection<int, Vehicle>  $vehicles
     * @param  Collection<int, StockItem>  $tracked
     */
    private function writePlateSale(Collection $vehicles, Collection $tracked, ?CashCategory $income): void
    {
        $vehicle = $vehicles->random();

        if (! $vehicle->ownershipOn($this->day)) {
            return;
        }

        $item = $tracked->random();
        $available = $this->exitAvailable($item);

        if ($available <= 0) {
            return;
        }

        $qty = $this->exitQty($item, $available);
        $unitPrice = (float) ($item->sales_price ?: $this->stock->stock($item, $this->day)['average_cost'] * 1.2);

        $this->attempt('plaka satışı', fn () => $this->invoices->create([
            'direction' => InvoiceDirection::Sale->value,
            'recipient' => InvoiceRecipient::Plate->value,
            'plate' => $vehicle->plate,
            'invoice_no' => $this->ticketNo('P'),
            'issued_on' => $this->day->toDateString(),
            'description' => $item->name.' · plaka',
            'payment_status' => InvoicePaymentStatus::Unpaid->value,
            'lines' => [[
                'description' => $item->name,
                'stock_item_id' => $item->id,
                'quantity' => $this->qty($qty),
                'unit_price' => $this->money($unitPrice),
                'vat_rate' => '0',
                'cash_category_id' => $income?->id,
            ]],
        ], $this->user->id));
    }

    /**
     * @param  Collection<int, StockItem>  $tracked
     * @param  Collection<int, Site>  $sites
     * @param  Collection<int, Vehicle>  $vehicles
     */
    private function writeUsage(Collection $tracked, Collection $sites, Collection $vehicles, string $expenseCategoryId): void
    {
        $item = $tracked->random();
        $available = $this->exitAvailable($item);

        if ($available <= 0) {
            return;
        }

        $qty = $this->exitQty($item, $available);

        $this->attempt('stok kullanımı', fn () => $this->usages->create([
            'stock_item_id' => $item->id,
            'quantity' => $this->qty($qty),
            'used_on' => $this->day->toDateString(),
            'site_id' => $sites->isNotEmpty() && fake()->boolean(60) ? $sites->random()->id : null,
            'vehicle_id' => $vehicles->isNotEmpty() && fake()->boolean(50) ? $vehicles->random()->id : null,
            'cash_category_id' => $item->cash_category_id ?? $expenseCategoryId,
            'description' => 'Stok kullanım · '.$item->name,
        ]));
    }

    /**
     * @param  Collection<int, StockItem>  $tracked
     */
    private function writeCount(Collection $tracked): void
    {
        $item = $tracked->random();
        $book = $this->stock->stock($item, $this->day)['quantity'];

        if ($book <= 0) {
            return;
        }

        $this->attempt('stok sayımı', fn () => $this->stockCounts->create([
            'stock_item_id' => $item->id,
            'quantity' => $this->qty($book),
            'counted_on' => $this->day->toDateString(),
            'description' => 'Stok sayımı · '.$item->name,
        ]));
    }

    /**
     * @param  array{0: float, 1: float}  $share
     * @param  list<PaymentMethod>  $methods
     */
    private function payOpenInvoices(InvoiceDirection $direction, int $chance, array $share, string $description, array $methods): void
    {
        $open = Invoice::query()
            ->where('direction', $direction)
            ->where('is_draft', false)
            ->whereNull('deleted_at')
            ->whereNull('vehicle_id')
            ->with('payments')
            ->get()
            ->filter(fn (Invoice $invoice) => $invoice->remainingAmount() >= 1_000);

        foreach ($open as $invoice) {
            if (! fake()->boolean($chance)) {
                continue;
            }

            $amount = $this->roundDown($invoice->remainingAmount() * fake()->randomFloat(2, ...$share));

            if ($amount < 1) {
                continue;
            }

            $method = fake()->randomElement($methods);
            $payment = $this->withInstrumentDetails([
                'amount' => $this->money($amount),
                'occurred_on' => $this->day->toDateString(),
                'payment_method' => $method->value,
                'bank_id' => $this->bankIdFor($method),
                'description' => $description,
            ]);

            $this->attempt(mb_strtolower($description), fn () => $this->invoices->pay($invoice, $payment, $this->user->id));
        }
    }

    /**
     * @return array{payment_status: string, payment?: array<string, mixed>}
     */
    private function entryPayment(InvoiceDirection $direction): array
    {
        if (fake()->boolean(75)) {
            return ['payment_status' => InvoicePaymentStatus::Unpaid->value];
        }

        $method = fake()->randomElement([
            PaymentMethod::BankTransfer,
            PaymentMethod::BankTransfer,
            PaymentMethod::Cash,
            PaymentMethod::CreditCard,
        ]);

        return [
            'payment_status' => InvoicePaymentStatus::Paid->value,
            'payment' => $this->withInstrumentDetails([
                'occurred_on' => $this->day->toDateString(),
                'payment_method' => $method->value,
                'bank_id' => $this->bankIdFor($method),
            ]),
        ];
    }

    private function purchaseQty(StockItem $item): float
    {
        return match ($item->unit->value) {
            'lt', 'kg' => (float) fake()->numberBetween(20, 200),
            default => (float) fake()->numberBetween(2, 24),
        };
    }

    private function purchaseUnitCost(StockItem $item): float
    {
        $sale = (float) $item->sales_price;
        $base = $sale > 0 ? $sale * 0.7 : 100;

        return (float) max(1, (int) round($base));
    }

    private function exitAvailable(StockItem $item): float
    {
        $book = $this->stock->stock($item, $this->day)['quantity'];
        $buffer = match ($item->unit->value) {
            'lt', 'kg' => 5.0,
            default => 2.0,
        };

        return (float) max(0, (int) floor($book - $buffer));
    }

    private function exitQty(StockItem $item, float $available): float
    {
        $max = max(1, (int) floor($available));
        $want = match ($item->unit->value) {
            'lt', 'kg' => fake()->numberBetween(1, min(40, $max)),
            default => fake()->numberBetween(1, min(6, $max)),
        };

        return (float) $want;
    }

    private function qty(float $value): string
    {
        return number_format(round($value, 3), 3, '.', '');
    }

    private function money(float $value): string
    {
        return number_format(round($value, 2), 2, '.', '');
    }

    private function market(): void
    {
        $sites = DB::table('sites')->whereNull('deleted_at')->where('active', true)->pluck('id');
        $trucks = Vehicle::query()->where('active', true)->whereHas('vehicleClass', fn ($query) => $query->where('hourly', false))->pluck('plate');

        foreach (Party::query()->withRole(PartyRole::Market)->where('active', true)->get() as $market) {
            if ($trucks->isNotEmpty() && $sites->isNotEmpty() && fake()->boolean(12)) {
                $this->cari('piyasa seferi', [
                    'kind' => 'market',
                    'direction' => 'debit',
                    'party_id' => $market->id,
                    'plate' => $trucks->random(),
                    'site_id' => $sites->random(),
                    'amount' => $this->roundDown(fake()->numberBetween(1_500, 15_000)),
                    'description' => 'Piyasa seferi',
                ]);
            }

            $open = $this->companyBalance(PartyRole::Market, $market->id);

            if ($this->day->day === 10 && $open >= 1_000) {
                $method = fake()->boolean(60) ? PaymentMethod::Cash : PaymentMethod::BankTransfer;
                $this->cari('piyasa tahsilatı', [
                    'kind' => 'market',
                    'direction' => 'credit',
                    'party_id' => $market->id,
                    'amount' => $this->roundDown($open * fake()->randomFloat(2, 0.7, 1.0)),
                    'payment_method' => $method->value,
                    'cash_category_id' => $this->category(CashDirection::Income)?->id,
                    'description' => 'Piyasa tahsilatı',
                ]);
            }
        }
    }

    private function siteMovements(): void
    {
        foreach (DB::table('sites')->whereNull('deleted_at')->where('active', true)->pluck('id') as $siteId) {
            if (fake()->boolean(8)) {
                $method = fake()->boolean(70) ? PaymentMethod::Cash : PaymentMethod::BankTransfer;
                $this->cari('şantiye gideri', [
                    'kind' => 'site',
                    'direction' => 'debit',
                    'site_id' => $siteId,
                    'amount' => $this->roundDown(fake()->numberBetween(1_500, 25_000)),
                    'payment_method' => $method->value,
                    'cash_category_id' => $this->category(CashDirection::Expense)?->id,
                    'description' => fake()->randomElement(['Yemek', 'İçme suyu', 'Elektrik', 'Şantiye malzemesi', 'Konteyner kirası']),
                ]);
            }

            if (fake()->boolean(1)) {
                $this->cari('şantiye geliri', [
                    'kind' => 'site',
                    'direction' => 'credit',
                    'site_id' => $siteId,
                    'amount' => $this->roundDown(fake()->numberBetween(2_000, 20_000)),
                    'payment_method' => PaymentMethod::Cash->value,
                    'cash_category_id' => $this->category(CashDirection::Income)?->id,
                    'description' => fake()->randomElement(['Hurda satışı', 'Ekipman kira geliri']),
                ]);
            }
        }
    }

    /**
     * On the 1st, paid operators get most of last month's operator earnings.
     */
    private function operatorSettlements(): void
    {
        if ($this->day->day !== 1) {
            return;
        }

        $earnings = MachineWork::query()->toBase()
            ->whereNull('deleted_at')
            ->whereNotNull('operator_amount')
            ->whereBetween('worked_on', [$this->day->copy()->subMonth()->toDateString(), $this->day->copy()->subDay()->toDateString()])
            ->groupBy('operator_id')
            ->pluck(DB::raw('SUM(operator_amount)'), 'operator_id');

        foreach ($earnings as $partyId => $amount) {
            if ((float) $amount >= 3_000) {
                $this->cari('operatör ödemesi', [
                    'kind' => 'owner_payment',
                    'party_id' => $partyId,
                    'amount' => $this->roundDown((float) $amount * fake()->randomFloat(2, 0.6, 0.9)),
                    'payment_method' => fake()->boolean(70) ? PaymentMethod::BankTransfer->value : PaymentMethod::Cash->value,
                    'cash_category_id' => $this->category(CashDirection::Expense)?->id,
                    'description' => 'Operatörlük ödemesi',
                ]);
            }
        }
    }

    /**
     * Last month's salary is paid on each person's pay day (3rd–10th), sometimes in two parts;
     * a bonus is written now and then.
     */
    private function payroll(): void
    {
        $period = $this->day->copy()->subMonth()->startOfMonth();

        foreach (Employee::query()->where('active', true)->get() as $employee) {
            $payDay = 3 + crc32($employee->id) % 8;
            $due = $this->salaries->balanceSummary($employee->id, $period->toDateString())['balance'];

            if (($this->day->day === $payDay || $this->day->day === $payDay + 7) && $due >= 100) {
                $amount = $this->day->day === $payDay && fake()->boolean(15) ? round($due * fake()->randomFloat(2, 0.4, 0.7), 2) : $due;

                $method = fake()->randomElement([PaymentMethod::BankTransfer, PaymentMethod::BankTransfer, PaymentMethod::Cash]);
                $this->attempt('personel ödemesi', fn () => $this->employeePayments->create([
                    'employee_id' => $employee->id,
                    'amount' => $amount,
                    'payment_method' => $method->value,
                    'bank_id' => $this->bankIdFor($method),
                    'year' => $period->year,
                    'month' => $period->month,
                    'occurred_on' => $this->day->toDateString(),
                    'description' => $amount < $due ? $period->translatedFormat('F Y').' maaşı (kısmi)' : $period->translatedFormat('F Y').' maaşı',
                ]));
            }

            if (fake()->boolean(1)) {
                $this->attempt('prim', fn () => $this->bonuses->create([
                    'employee_id' => $employee->id,
                    'amount' => fake()->randomElement([1500, 2500, 4000, 5000, 7500]),
                    'occurred_on' => $this->day->toDateString(),
                    'description' => fake()->randomElement(['Saha pirimi', 'Performans', 'Fazla mesai pirimi']),
                ]));
            }
        }
    }

    /**
     * A little office spending every day; when the drawer runs low, bank→cash transfer (39);
     * when it runs high, cash→bank deposit. No fake "Bankadan çekilen" cash rows.
     */
    private function cashDrawer(): void
    {
        for ($n = fake()->numberBetween(0, 2); $n > 0; $n--) {
            $this->cari('kasa gideri', [
                'kind' => 'cash',
                'direction' => 'credit',
                'amount' => $this->roundDown(fake()->numberBetween(300, 5_000)),
                'cash_category_id' => $this->category(CashDirection::Expense)?->id,
                'description' => fake()->randomElement(['Kırtasiye', 'Çay ocağı', 'Kargo', 'Yakıt avansı', 'Temizlik']),
            ]);
        }

        $drawer = $this->cash->report(['period' => 'range', 'from' => '2000-01-01', 'to' => $this->day->toDateString()])['closing'];
        $banks = Bank::query()->where('active', true)->where('type', AccountType::Bank)->pluck('id');
        $cashBoxes = Bank::query()->where('active', true)->where('type', AccountType::Cash)->pluck('id');

        if ($banks->isEmpty() || $cashBoxes->isEmpty()) {
            return;
        }

        if ($drawer < 100_000) {
            $amount = $this->roundDown(200_000 - $drawer + fake()->numberBetween(0, 100_000));

            if ($amount > 0) {
                $this->cari('bankadan kasaya', [
                    'kind' => 'account_transfer',
                    'bank_id' => $banks->random(),
                    'counter_bank_id' => $cashBoxes->random(),
                    'amount' => $amount,
                    'description' => 'Bankadan kasaya çekim',
                ]);
            }
        } elseif ($drawer > 400_000) {
            $amount = $this->roundDown($drawer - fake()->numberBetween(150_000, 250_000));

            if ($amount > 0) {
                $this->cari('kasadan bankaya', [
                    'kind' => 'account_transfer',
                    'bank_id' => $cashBoxes->random(),
                    'counter_bank_id' => $banks->random(),
                    'amount' => $amount,
                    'description' => 'Kasadan bankaya yatırma',
                ]);
            }
        }
    }

    /**
     * Debit − credit on the party's firm account for the role up to today (positive: they owe us).
     */
    private function companyBalance(PartyRole $role, string $partyId): float
    {
        return round((float) DB::table('company_transactions')
            ->whereNull('deleted_at')
            ->where('role', $role->value)
            ->where('party_id', $partyId)
            ->where('occurred_on', '<=', $this->day->toDateString())
            ->sum(DB::raw('debit - credit')), 2);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function cari(string $label, array $data): void
    {
        if ((float) $data['amount'] <= 0) {
            return;
        }

        if (empty($data['bank_id']) && ($method = PaymentMethod::tryFrom((string) ($data['payment_method'] ?? '')))) {
            $data['bank_id'] = $this->bankIdFor($method);
        }

        $data = $this->withInstrumentDetails($data);

        $this->attempt($label, fn () => $this->entries->create([
            'occurred_on' => $this->day->toDateString(),
            ...$data,
            'amount' => number_format((float) $data['amount'], 2, '.', ''),
        ], $this->user->id));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function withInstrumentDetails(array $data): array
    {
        $method = PaymentMethod::tryFrom((string) ($data['payment_method'] ?? ''));

        if ($method === null || ! $method->hasDueDate()) {
            return $data;
        }

        if (empty($data['cheque_no'])) {
            $prefix = $method === PaymentMethod::PromissoryNote ? 'SN' : 'CK';
            $data['cheque_no'] = $this->ticketNo($prefix);
        }

        if (empty($data['due_on'])) {
            $occurred = Carbon::parse((string) ($data['occurred_on'] ?? $this->day->toDateString()));
            $data['due_on'] = $occurred->copy()->addDays(fake()->numberBetween(7, 90))->toDateString();
        }

        return $data;
    }

    private function bankIdFor(PaymentMethod $method): ?string
    {
        $types = $method->accountTypes();

        if ($types === []) {
            return null;
        }

        return Bank::query()
            ->where('active', true)
            ->whereIn('type', $types)
            ->inRandomOrder()
            ->value('id');
    }

    private function attempt(string $label, callable $write): void
    {
        try {
            $write();
            $this->counts[$label] = ($this->counts[$label] ?? 0) + 1;
        } catch (OperationException|OperationRejectedException|RuntimeException $exception) {
            $this->skipped[] = "{$label}: {$exception->getMessage()}";
        }
    }

    /**
     * Mostly yesterday's route; sometimes another dump of the same site, rarely another site.
     *
     * @param  Collection<int, HaulRoute>  $routes
     */
    private function nextRoute(?HaulRoute $current, Collection $routes): HaulRoute
    {
        if (! $current || fake()->boolean(10)) {
            $sameSite = $current ? $routes->where('site_id', $current->site_id)->where('id', '<>', $current->id) : collect();

            return $sameSite->isNotEmpty() && fake()->boolean(65) ? $sameSite->random() : $routes->random();
        }

        return $current;
    }

    /**
     * Average trips one truck makes on each route in a working day, over the last 60 days.
     *
     * @return array<string, float>
     */
    private function routeTripsPerDay(): array
    {
        return Haul::query()->toBase()
            ->whereNull('deleted_at')
            ->where('hauled_at', '>=', $this->day->copy()->subDays(60))
            ->where('hauled_at', '<', $this->day)
            ->selectRaw('route_id, COUNT(*) * 1.0 / COUNT(DISTINCT (CAST(vehicle_id AS TEXT) || substr(CAST(hauled_at AS TEXT), 1, 10))) as trips')
            ->groupBy('route_id')
            ->pluck('trips', 'route_id')
            ->map(fn ($trips) => (float) $trips)
            ->all();
    }

    private function stationFor(string $siteId): ?FuelStation
    {
        $linked = DB::table('fuel_station_sites')->where('site_id', $siteId)->pluck('fuel_station_id');

        return FuelStation::query()
            ->where('active', true)
            ->where('unit_price', '>', 0)
            ->when($linked->isNotEmpty(), fn ($query) => $query->whereIn('id', $linked))
            ->inRandomOrder()
            ->first();
    }

    private function category(CashDirection $direction): ?CashCategory
    {
        return CashCategory::query()
            ->whereIn('direction', [$direction->value, CashDirection::Both->value])
            ->inRandomOrder()
            ->first();
    }

    private function ticketNo(string $prefix): string
    {
        return sprintf('%s-%s-%04d', $prefix, $this->day->format('ymd'), ++$this->ticket).Str::lower(Str::random(2));
    }

    private function roundDown(float $amount): float
    {
        return floor($amount / 50) * 50;
    }
}
