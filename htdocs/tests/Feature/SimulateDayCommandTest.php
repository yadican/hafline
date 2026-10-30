<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Enums\CashDirection;
use App\Enums\InvoiceDirection;
use App\Enums\InvoicePaymentStatus;
use App\Enums\PartyRole;
use App\Models\AccountTransfer;
use App\Models\Bank;
use App\Models\CashCategory;
use App\Models\CashEntry;
use App\Models\CompanyTransaction;
use App\Models\DumpSiteClassPrice;
use App\Models\FuelStation;
use App\Models\Haul;
use App\Models\HaulRoute;
use App\Models\Invoice;
use App\Models\MachineWork;
use App\Models\Party;
use App\Models\RouteClassPrice;
use App\Models\StockItem;
use App\Models\Vehicle;
use App\Models\VehicleClass;
use App\Services\InvoiceService;
use App\Services\StockService;
use Database\Seeders\CashCategorySeeder;
use Database\Seeders\LoadTest\DailyStage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use ReflectionProperty;
use Tests\TestCase;

class SimulateDayCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        fake()->seed(7);

        $owner = Party::factory()->create();
        $route = HaulRoute::factory()->create();
        $class = VehicleClass::factory()->create(['name' => 'TIR']);
        RouteClassPrice::query()->create(['route_id' => $route->id, 'vehicle_class_id' => $class->id, 'price' => 2500]);
        DumpSiteClassPrice::query()->create(['dump_site_id' => $route->dump_site_id, 'vehicle_class_id' => $class->id, 'price' => 400]);

        $station = FuelStation::factory()->create(['unit_price' => 50, 'purchase_price' => 42]);
        DB::table('fuel_station_sites')->insert(['fuel_station_id' => $station->id, 'site_id' => $route->site_id]);

        foreach (range(1, 5) as $n) {
            Vehicle::factory()->ownedBy($owner, '2026-01-01')->create(['plate' => "34 TR 10{$n}", 'vehicle_class_id' => $class->id]);
        }

        $machine = Vehicle::factory()
            ->ownedBy($owner, '2026-01-01')
            ->create(['plate' => '34 MK 100', 'vehicle_class_id' => VehicleClass::factory()->hourly()]);
        $machine->rates()->create(['hourly_rate' => 1000, 'started_on' => '2026-01-01']);
    }

    public function test_a_simulated_day_is_entered_through_the_services_and_keeps_the_cari_consistent(): void
    {
        $this->artisan('simulate:day', ['date' => '2026-03-10', '--no-audit' => true])
            ->expectsOutputToContain('10.03.2026')
            ->assertSuccessful();

        $user = DailyStage::user();

        $this->assertGreaterThan(0, Haul::query()->where('created_by', $user->id)->count());
        $this->assertTrue(Haul::query()->get()->every(fn (Haul $haul) => $haul->hauled_at->isSameDay('2026-03-10') && (float) $haul->price === 2500.0));
        $this->assertSame(1, MachineWork::query()->where('created_by', $user->id)->count());
        $this->assertFalse($user->active, 'Simülasyon kullanıcısı panele giriş yapamaz.');

        $this->artisan('ledger:audit', ['--skip-reports' => true])->assertSuccessful();
    }

    public function test_the_same_day_is_not_simulated_twice_and_future_days_are_refused(): void
    {
        $this->artisan('simulate:day', ['date' => '2026-03-10', '--no-audit' => true])->assertSuccessful();
        $hauls = Haul::query()->count();

        $this->artisan('simulate:day', ['date' => '2026-03-10', '--no-audit' => true])
            ->expectsOutputToContain('zaten simüle edilmiş')
            ->assertSuccessful();
        $this->assertSame($hauls, Haul::query()->count());

        $this->artisan('simulate:day', ['date' => now()->addDays(2)->toDateString(), '--no-audit' => true])
            ->expectsOutputToContain('henüz gelmedi')
            ->assertSuccessful();
    }

    public function test_consecutive_days_continue_from_the_previous_day(): void
    {
        $this->artisan('simulate:day', ['date' => '2026-03-09', '--days' => 3, '--no-audit' => true])->assertSuccessful();

        $days = Haul::query()->get()->map(fn (Haul $haul) => $haul->hauled_at->toDateString())->unique()->sort()->values()->all();

        $this->assertSame(['2026-03-09', '2026-03-10', '2026-03-11'], $days);
        $this->artisan('ledger:audit', ['--skip-reports' => true])->assertSuccessful();
    }

    public function test_cash_drawer_tops_up_via_account_transfer_not_fake_cash_row(): void
    {
        $this->seed(CashCategorySeeder::class);
        Bank::factory()->create(['type' => AccountType::Cash, 'name' => 'Merkez Kasa']);
        Bank::factory()->create(['type' => AccountType::Bank, 'name' => 'Ziraat']);

        $this->artisan('simulate:day', ['date' => '2026-03-10', '--no-audit' => true])->assertSuccessful();

        $this->assertGreaterThan(0, AccountTransfer::query()->where('description', 'Bankadan kasaya çekim')->count());
        $this->assertSame(0, CashEntry::query()->whereIn('description', ['Bankadan nakit çekim', 'Bankadan çekilen'])->count());
        $this->artisan('ledger:audit', ['--skip-reports' => true])->assertSuccessful();
    }

    public function test_supplier_stock_and_plate_sales_go_through_invoices_not_fake_cari(): void
    {
        $this->seed(CashCategorySeeder::class);
        Bank::factory()->create(['type' => AccountType::Cash, 'name' => 'Merkez Kasa']);
        Bank::factory()->create(['type' => AccountType::Bank, 'name' => 'Ziraat']);

        $supplier = Party::factory()->company()->withRole(PartyRole::Supplier)->create();
        $customer = Party::factory()->company()->withRole(PartyRole::Customer)->create();
        $item = StockItem::factory()->create(['name' => 'Filtre', 'sales_price' => 100, 'tracks_stock' => true]);
        $expense = CashCategory::query()->where('direction', CashDirection::Expense)->first();
        $income = CashCategory::query()->where('direction', CashDirection::Income)->first();
        $day = Carbon::parse('2026-03-12');
        $user = DailyStage::user();

        app(InvoiceService::class)->create([
            'direction' => InvoiceDirection::Purchase->value,
            'party_id' => $supplier->id,
            'invoice_no' => 'A-TEST-001',
            'issued_on' => $day->toDateString(),
            'payment_status' => InvoicePaymentStatus::Unpaid->value,
            'lines' => [[
                'description' => $item->name,
                'stock_item_id' => $item->id,
                'quantity' => '20.000',
                'unit_price' => '70.00',
                'vat_rate' => '0',
                'cash_category_id' => $expense?->id,
            ]],
        ], $user->id);

        $this->assertGreaterThan(10, app(StockService::class)->stock($item->fresh(), $day)['quantity']);

        $stage = app(DailyStage::class);
        $this->bindDay($stage, $day);

        (new ReflectionMethod(DailyStage::class, 'writePurchase'))->invoke(
            $stage,
            $supplier,
            collect([$item]),
            null,
            $expense,
            collect(),
        );

        $vehicles = Vehicle::query()->where('active', true)->whereHas('vehicleClass', fn ($q) => $q->where('hourly', false))->get();
        (new ReflectionMethod(DailyStage::class, 'writePlateSale'))->invoke($stage, $vehicles, collect([$item]), $income);
        (new ReflectionMethod(DailyStage::class, 'writeCustomerSale'))->invoke(
            $stage,
            collect([$customer]),
            collect([$item]),
            null,
            $income,
        );

        $this->assertGreaterThan(1, Invoice::query()->where('direction', InvoiceDirection::Purchase)->count());
        $this->assertGreaterThan(0, Invoice::query()->where('direction', InvoiceDirection::Sale)->whereNotNull('vehicle_id')->count());
        $this->assertGreaterThan(0, Invoice::query()->where('direction', InvoiceDirection::Sale)->whereNull('vehicle_id')->count());
        $this->assertSame(
            0,
            CompanyTransaction::query()
                ->where('role', PartyRole::Supplier)
                ->where('credit', '>', 0)
                ->whereNull('payment_method')
                ->count(),
            'Eski sahte tedarikçi fatura cari satırı olmamalı.',
        );
        $this->assertGreaterThanOrEqual(0, app(StockService::class)->stock($item->fresh(), $day)['quantity']);

        $this->artisan('simulate:day', ['date' => '2026-03-10', '--no-audit' => true])->assertSuccessful();
        $this->assertSame(
            0,
            CompanyTransaction::query()
                ->where('role', PartyRole::Supplier)
                ->where('credit', '>', 0)
                ->whereNull('payment_method')
                ->count(),
        );

        $this->artisan('ledger:audit', ['--skip-reports' => true])->assertSuccessful();
    }

    private function bindDay(DailyStage $stage, Carbon $day): void
    {
        $user = DailyStage::user();
        Auth::setUser($user);

        (new ReflectionProperty(DailyStage::class, 'day'))->setValue($stage, $day->copy()->startOfDay());
        (new ReflectionProperty(DailyStage::class, 'cutoff'))->setValue($stage, $day->copy()->endOfDay());
        (new ReflectionProperty(DailyStage::class, 'dayType'))->setValue($stage, 0);
        (new ReflectionProperty(DailyStage::class, 'user'))->setValue($stage, $user);
        (new ReflectionProperty(DailyStage::class, 'ticket'))->setValue($stage, 0);
        (new ReflectionProperty(DailyStage::class, 'counts'))->setValue($stage, []);
        (new ReflectionProperty(DailyStage::class, 'skipped'))->setValue($stage, []);
    }
}
