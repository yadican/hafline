<?php

namespace Tests\Feature;

use App\Enums\InvoiceDirection;
use App\Enums\PartyRole;
use App\Enums\PaymentMethod;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\LedgerEntry;
use App\Models\Party;
use App\Models\StockItem;
use App\Models\StockUsage;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\InvoiceService;
use App\Services\StockCountService;
use App\Services\StockService;
use App\Services\StockUsageService;
use Database\Seeders\CashCategorySeeder;
use Database\Seeders\LoadTest\CariStage;
use Database\Seeders\LoadTest\CatalogStage;
use Database\Seeders\LoadTest\InvoiceStage;
use Database\Seeders\LoadTest\Support\Progress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use ReflectionMethod;
use ReflectionProperty;
use Tests\TestCase;

class LoadTestInvoiceStockTest extends TestCase
{
    use RefreshDatabase;

    private CatalogStage $catalog;

    private InvoiceStage $stage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CashCategorySeeder::class);
        User::factory()->yonetici()->create();

        $this->catalog = new CatalogStage(new Progress(null));
        (new ReflectionMethod(CatalogStage::class, 'seedBanks'))->invoke(
            $this->catalog,
            Carbon::parse('2026-01-01'),
        );
        (new ReflectionMethod(CatalogStage::class, 'seedStockItems'))->invoke($this->catalog);

        $suppliers = Party::factory()->company()->withRole(PartyRole::Supplier)->count(2)->create();
        $customers = Party::factory()->company()->withRole(PartyRole::Customer)->count(2)->create();
        $owners = Party::factory()->person()->withRole(PartyRole::VehicleOwner)->count(2)->create();

        (new ReflectionProperty(CatalogStage::class, 'partiesByRole'))->setValue($this->catalog, [
            PartyRole::Supplier->value => $suppliers,
            PartyRole::Customer->value => $customers,
            PartyRole::VehicleOwner->value => $owners,
        ]);
        (new ReflectionProperty(CatalogStage::class, 'sites'))->setValue($this->catalog, collect());

        $ownershipIndex = [];
        $vehicles = collect();
        foreach ($owners as $owner) {
            $vehicle = Vehicle::factory()->ownedBy($owner, '2026-01-01')->create();
            $vehicles->push($vehicle);
            $ownershipIndex[$vehicle->id] = [[
                'party_id' => $owner->id,
                'started_on' => '2026-01-01',
                'ended_on' => null,
            ]];
        }
        (new ReflectionProperty(CatalogStage::class, 'ownershipIndex'))->setValue($this->catalog, $ownershipIndex);
        (new ReflectionProperty(CatalogStage::class, 'haulVehicles'))->setValue($this->catalog, $vehicles);
        (new ReflectionProperty(CatalogStage::class, 'machines'))->setValue($this->catalog, collect());

        $this->stage = new InvoiceStage(
            new Progress(null),
            app(InvoiceService::class),
            app(StockService::class),
            app(StockUsageService::class),
            app(StockCountService::class),
        );
    }

    public function test_catalog_seeds_tracked_and_untracked_stock_items(): void
    {
        $items = $this->catalog->stockItems();

        $this->assertGreaterThanOrEqual(12, $items->count());
        $this->assertTrue($items->contains(fn (StockItem $item) => $item->tracks_stock));
        $this->assertTrue($items->contains(fn (StockItem $item) => ! $item->tracks_stock));
        $this->assertSame(
            $items->where('tracks_stock', true)->count(),
            $this->catalog->trackedStockItems()->count(),
        );
    }

    public function test_purchases_post_supplier_cari_and_raise_stock(): void
    {
        fake()->seed(45);

        $from = Carbon::parse('2026-01-01');
        $to = Carbon::parse('2026-01-31');
        $tracked = $this->catalog->trackedStockItems();
        $service = $this->catalog->stockItems()->firstWhere('tracks_stock', false);

        $count = $this->stage->seedPurchases(
            $this->catalog,
            $from,
            $to,
            User::query()->value('id'),
            $tracked,
            $service,
        );

        $this->assertGreaterThan(0, $count);
        $this->assertSame($count, Invoice::query()->where('direction', InvoiceDirection::Purchase)->count());

        $purchase = Invoice::query()->where('direction', InvoiceDirection::Purchase)->firstOrFail();
        $credit = LedgerEntry::query()
            ->where('party_id', $purchase->party_id)
            ->where('source_type', 'invoice')
            ->where('source_id', $purchase->id)
            ->sole();
        $this->assertSame($purchase->total, $credit->credit);
        $this->assertSame('0.00', $credit->debit);

        $stocked = $tracked->first(
            fn (StockItem $item) => app(StockService::class)->stock($item, $to)['quantity'] > 0,
        );
        $this->assertNotNull($stocked, 'Alış sonrası en az bir kalemde stok olmalı.');
    }

    public function test_plate_sales_usages_and_payments_keep_stock_and_ledger_consistent(): void
    {
        fake()->seed(450);

        $from = Carbon::parse('2026-01-01');
        $to = Carbon::parse('2026-03-31');
        $userId = User::query()->value('id');
        $tracked = $this->catalog->trackedStockItems();
        $service = $this->catalog->stockItems()->firstWhere('tracks_stock', false);

        $this->stage->seedPurchases($this->catalog, $from, $to, $userId, $tracked, $service);
        $exits = $this->stage->seedExitsChronologically($this->catalog, $from, $to, $userId, $tracked, $service);
        $this->stage->seedPayments($this->catalog, $to, $userId);

        $this->assertGreaterThan(0, $exits['plate'] + $exits['sales']);
        $this->assertGreaterThan(0, $exits['usages']);

        $this->assertTrue(
            Invoice::query()->where('direction', InvoiceDirection::Sale)->whereNotNull('vehicle_id')->exists(),
            'Plakaya satış faturası olmalı.',
        );
        $this->assertGreaterThan(0, StockUsage::query()->count());
        $this->assertGreaterThan(0, InvoicePayment::query()->count());

        foreach ($tracked as $item) {
            $this->assertGreaterThanOrEqual(
                0,
                app(StockService::class)->stock($item, $to)['quantity'],
                $item->name.' eksi stokta.',
            );
        }

        $this->artisan('ledger:audit', ['--skip-reports' => true])
            ->expectsOutputToContain('Stok miktarı ve değeri hareketlerle tutarlı · kontrol')
            ->expectsOutputToContain('uyumsuz 0')
            ->expectsOutputToContain('Cari, belgeler ve raporlar birebir tutuyor.')
            ->assertSuccessful();
    }

    public function test_deferred_payments_attach_accounts_for_money_methods(): void
    {
        fake()->seed(451);

        $from = Carbon::parse('2026-01-01');
        $to = Carbon::parse('2026-02-28');
        $userId = User::query()->value('id');
        $tracked = $this->catalog->trackedStockItems();

        $this->stage->seedPurchases($this->catalog, $from, $to, $userId, $tracked, null);
        $paid = $this->stage->seedPayments($this->catalog, $to, $userId);

        $this->assertGreaterThan(0, $paid);

        $methodsNeedingAccount = [
            PaymentMethod::Cash->value,
            PaymentMethod::BankTransfer->value,
            PaymentMethod::CreditCard->value,
        ];

        $entries = LedgerEntry::query()
            ->where('source_type', 'company_transaction')
            ->whereIn('payment_method', $methodsNeedingAccount)
            ->get();

        $this->assertNotEmpty($entries);
        $this->assertTrue(
            $entries->every(fn (LedgerEntry $entry) => $entry->bank_id !== null),
            'Para hareketi olan fatura ödemelerinde bank_id olmalı (38b).',
        );
    }

    public function test_cari_stage_no_longer_queues_fake_supplier_invoices(): void
    {
        $this->assertFalse(method_exists(CariStage::class, 'queueSuppliers'));
    }
}
