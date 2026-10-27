<?php

namespace Database\Seeders\LoadTest;

use App\Enums\CashDirection;
use App\Enums\PartyRole;
use App\Enums\PaymentMethod;
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
use App\Models\LedgerEntry;
use App\Models\MachineWork;
use App\Models\Party;
use App\Models\SiteTransaction;
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
use App\Services\MachineWorkService;
use App\Services\Reports\CashMovementReportService;
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
 * and 16th, fuel firms invoice every half month, sites are billed at month end, staff are paid
 * early in the month and the cash drawer is topped up from the bank. A few tickets are corrected
 * or cancelled and a vehicle is sometimes sold, so reversals and transfer closings happen too.
 * Times that have not come yet today are not written.
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
        $this->customerBilling();
        $this->suppliersAndMarket();
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
     * Fuel firms invoice the receipts of the previous half month on the 1st and 16th; open
     * invoices are paid on Wednesdays.
     */
    private function fuelBilling(): void
    {
        if (in_array($this->day->day, [1, 16], true)) {
            [$from, $to] = $this->day->day === 1
                ? [$this->day->copy()->subMonth()->day(16), $this->day->copy()->subDay()]
                : [$this->day->copy()->day(1), $this->day->copy()->day(15)];

            $groups = FuelReceipt::query()->toBase()
                ->whereNull('deleted_at')
                ->whereBetween('issued_on', [$from->toDateString(), $to->toDateString()])
                ->groupBy('fuel_station_id', 'fuel_party_id', 'site_id')
                ->get(['fuel_station_id', 'fuel_party_id', 'site_id', DB::raw('SUM(quantity) as liters'), DB::raw('SUM(amount) as total')]);

            foreach ($groups as $group) {
                if ((float) $group->total < 1_000 || $group->fuel_party_id === null) {
                    continue;
                }

                $this->cari('akaryakıt faturası', [
                    'kind' => 'fuel',
                    'direction' => 'credit',
                    'party_id' => $group->fuel_party_id,
                    'site_id' => $group->site_id,
                    'fuel_station_id' => $group->fuel_station_id,
                    'amount' => round((float) $group->total, 2),
                    'liters' => round((float) $group->liters, 2),
                    'unit_price' => round((float) $group->total / max(1, (float) $group->liters), 4),
                    'document_no' => $this->ticketNo('AK'),
                    'description' => 'Akaryakıt faturası ('.$from->format('d.m').'–'.$to->format('d.m').')',
                ]);
            }
        }

        if ($this->day->dayOfWeek === Carbon::WEDNESDAY) {
            $this->payOpenCompanyBalances(PartyRole::Fuel, 70, [0.6, 0.9], 'Akaryakıt ödemesi', [PaymentMethod::BankTransfer, PaymentMethod::BankTransfer, PaymentMethod::Cheque]);
        }
    }

    /**
     * Sites are billed at month end from their hauls and machine hours plus margin; customers
     * pay part of what they owe now and then.
     */
    private function customerBilling(): void
    {
        $customers = DB::table('site_parties')->where('role', PartyRole::Customer->value)->get();

        if ($this->day->isLastOfMonth()) {
            $from = $this->day->copy()->startOfMonth();

            foreach ($customers as $link) {
                $revenue = (float) Haul::query()->where('site_id', $link->site_id)->whereBetween('hauled_at', [$from, $this->day->copy()->endOfDay()])->sum('price')
                    + (float) MachineWork::query()->where('site_id', $link->site_id)->whereBetween('worked_on', [$from->toDateString(), $this->day->toDateString()])->sum('amount');

                if ($revenue >= 10_000) {
                    $this->cari('hakediş', [
                        'kind' => 'customer',
                        'direction' => 'debit',
                        'party_id' => $link->party_id,
                        'site_id' => $link->site_id,
                        'amount' => $this->roundDown($revenue * fake()->randomFloat(2, 1.18, 1.32)),
                        'document_no' => $this->ticketNo('HK'),
                        'description' => $this->day->translatedFormat('F Y').' hakedişi',
                    ]);
                }
            }
        }

        foreach ($customers->pluck('party_id')->unique() as $partyId) {
            $open = $this->companyBalance(PartyRole::Customer, $partyId);

            if ($open >= 10_000 && fake()->boolean(6)) {
                $this->cari('hakediş tahsilatı', [
                    'kind' => 'customer',
                    'direction' => 'credit',
                    'party_id' => $partyId,
                    'amount' => $this->roundDown($open * fake()->randomFloat(2, 0.3, 0.7)),
                    'payment_method' => fake()->randomElement([PaymentMethod::BankTransfer, PaymentMethod::BankTransfer, PaymentMethod::Cheque, PaymentMethod::PromissoryNote])->value,
                    'description' => 'Hakediş tahsilatı',
                ]);
            }
        }
    }

    private function suppliersAndMarket(): void
    {
        $sites = DB::table('sites')->whereNull('deleted_at')->where('active', true)->pluck('id');

        foreach (Party::query()->withRole(PartyRole::Supplier)->where('active', true)->get() as $supplier) {
            if (fake()->boolean(7)) {
                $this->cari('tedarikçi faturası', [
                    'kind' => 'supplier',
                    'direction' => 'credit',
                    'party_id' => $supplier->id,
                    'site_id' => $sites->random(),
                    'amount' => $this->roundDown(fake()->numberBetween(5_000, 60_000)),
                    'document_no' => $this->ticketNo('ST'),
                    'description' => fake()->randomElement(['Kum', 'Çakıl', 'Lastik', 'Yedek parça', 'Madeni yağ', 'Filtre']),
                ]);
            }
        }

        if ($this->day->dayOfWeek === Carbon::TUESDAY) {
            $this->payOpenCompanyBalances(PartyRole::Supplier, 50, [0.7, 1.0], 'Tedarikçi ödemesi', [PaymentMethod::BankTransfer, PaymentMethod::BankTransfer, PaymentMethod::Cash, PaymentMethod::CreditCard]);
        }

        $trucks = Vehicle::query()->where('active', true)->whereHas('vehicleClass', fn ($query) => $query->where('hourly', false))->pluck('plate');

        foreach (Party::query()->withRole(PartyRole::Market)->where('active', true)->get() as $market) {
            if ($trucks->isNotEmpty() && fake()->boolean(12)) {
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
                $this->cari('piyasa tahsilatı', [
                    'kind' => 'market',
                    'direction' => 'credit',
                    'party_id' => $market->id,
                    'amount' => $this->roundDown($open * fake()->randomFloat(2, 0.7, 1.0)),
                    'payment_method' => fake()->boolean(60) ? PaymentMethod::Cash->value : PaymentMethod::BankTransfer->value,
                    'description' => 'Piyasa tahsilatı',
                ]);
            }
        }
    }

    private function siteMovements(): void
    {
        foreach (DB::table('sites')->whereNull('deleted_at')->where('active', true)->pluck('id') as $siteId) {
            if (fake()->boolean(8)) {
                $this->cari('şantiye gideri', [
                    'kind' => 'site',
                    'direction' => 'debit',
                    'site_id' => $siteId,
                    'amount' => $this->roundDown(fake()->numberBetween(1_500, 25_000)),
                    'payment_method' => fake()->boolean(70) ? PaymentMethod::Cash->value : PaymentMethod::BankTransfer->value,
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

                $this->attempt('personel ödemesi', fn () => $this->employeePayments->create([
                    'employee_id' => $employee->id,
                    'amount' => $amount,
                    'payment_method' => fake()->randomElement([PaymentMethod::BankTransfer, PaymentMethod::BankTransfer, PaymentMethod::Cash])->value,
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
     * A little office spending every day; when the drawer runs low, cash is drawn from the bank.
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

        if ($drawer < 100_000) {
            $this->cari('bankadan nakit', [
                'kind' => 'cash',
                'direction' => 'debit',
                'amount' => $this->roundDown(200_000 - $drawer + fake()->numberBetween(0, 100_000)),
                'cash_category_id' => $this->category(CashDirection::Income)?->id,
                'bank_id' => Bank::query()->where('active', true)->inRandomOrder()->value('id'),
                'description' => 'Bankadan nakit çekim',
            ]);
        }
    }

    /**
     * @param  array{0: float, 1: float}  $share
     * @param  list<PaymentMethod>  $methods
     */
    private function payOpenCompanyBalances(PartyRole $role, int $chance, array $share, string $description, array $methods): void
    {
        foreach (Party::query()->withRole($role)->get() as $party) {
            $open = -$this->companyBalance($role, $party->id);

            if ($open >= 1_000 && fake()->boolean($chance)) {
                $this->cari(mb_strtolower($description), [
                    'kind' => $role->value,
                    'direction' => 'debit',
                    'party_id' => $party->id,
                    'amount' => $this->roundDown($open * fake()->randomFloat(2, ...$share)),
                    'payment_method' => fake()->randomElement($methods)->value,
                    'cash_category_id' => $this->category(CashDirection::Expense)?->id,
                    'document_no' => $this->ticketNo('OD'),
                    'description' => $description,
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

        $this->attempt($label, fn () => $this->entries->create([
            'occurred_on' => $this->day->toDateString(),
            ...$data,
            'amount' => number_format((float) $data['amount'], 2, '.', ''),
        ], $this->user->id));
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
