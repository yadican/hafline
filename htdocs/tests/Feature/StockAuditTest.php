<?php

namespace Tests\Feature;

use App\Models\CashCategory;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Party;
use App\Models\Site;
use App\Models\StockCount;
use App\Models\StockItem;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\Ledger\LedgerAudit;
use App\Services\StockCountService;
use App\Services\StockService;
use App\Services\StockUsageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class StockAuditTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->yonetici()->create();
        Auth::login($this->admin);
    }

    public function test_audit_passes_for_purchase_sale_usage_and_count(): void
    {
        $item = StockItem::factory()->create(['name' => 'Çimento']);
        $this->purchase($item, '2026-03-01', '100', '40');
        $this->purchase($item, '2026-03-05', '100', '46');
        $this->sale($item, '2026-03-10', '50', '60');
        app(StockUsageService::class)->create([
            'stock_item_id' => $item->id,
            'quantity' => '10',
            'used_on' => '2026-03-12',
            'site_id' => Site::factory()->create()->id,
            'cash_category_id' => CashCategory::factory()->create()->id,
        ]);
        app(StockCountService::class)->create([
            'stock_item_id' => $item->id,
            'quantity' => '250',
            'counted_on' => '2026-03-15',
        ]);

        $this->assertSame(
            ['quantity' => 250.0, 'average_cost' => 43.0, 'value' => 10750.0],
            app(StockService::class)->stock($item),
        );

        $this->artisan('ledger:audit')
            ->expectsOutputToContain('Stok miktarı ve değeri hareketlerle tutarlı · kontrol 1 · uyumsuz 0')
            ->expectsOutputToContain('Cari, belgeler ve raporlar birebir tutuyor.')
            ->assertSuccessful();
    }

    public function test_audit_fails_when_a_day_runs_negative(): void
    {
        $item = StockItem::factory()->create(['name' => 'Filtre']);
        $this->purchase($item, '2026-04-01', '10', '30');

        $invoice = Invoice::factory()->sale()->create([
            'party_id' => Party::factory()->company(),
            'issued_on' => '2026-04-02',
            'subtotal' => 1500,
            'vat_amount' => 0,
        ]);
        $invoice->lines()->delete();
        InvoiceLine::factory()->create([
            'invoice_id' => $invoice->id,
            'stock_item_id' => $item->id,
            'quantity' => 50,
            'unit_price' => 30,
            'subtotal' => 1500,
            'vat_amount' => 0,
            'total' => 1500,
            'unit_cost' => 30,
        ]);

        $this->assertFailuresContain('Stok Filtre: 2026-04-02 tarihinde miktar eksiye düştü');
        $this->artisan('ledger:audit', ['--skip-reports' => true])
            ->expectsOutputToContain('Stok miktarı ve değeri hareketlerle tutarlı')
            ->assertFailed();
    }

    public function test_audit_fails_when_sale_unit_cost_no_longer_matches_average(): void
    {
        $item = StockItem::factory()->create(['name' => 'Yağ']);
        $this->purchase($item, '2026-05-01', '100', '40');
        $sale = $this->sale($item, '2026-05-10', '20', '60');

        InvoiceLine::query()->where('invoice_id', $sale->id)->update(['unit_cost' => 99]);

        $this->assertFailuresContain(
            'Stok Yağ: giriş değeri 4000.00, çıkış 1980.00 + kalan 3200.00 + sayım farkı 0.00',
        );
        $this->artisan('ledger:audit', ['--skip-reports' => true])->assertFailed();
    }

    public function test_audit_fails_when_count_quantity_no_longer_matches_book_plus_difference(): void
    {
        $item = StockItem::factory()->create(['name' => 'Cıvata']);
        $this->purchase($item, '2026-06-01', '100', '40');
        app(StockCountService::class)->create([
            'stock_item_id' => $item->id,
            'quantity' => '120',
            'counted_on' => '2026-06-05',
        ]);

        StockCount::query()->sole()->update(['quantity' => 90]);

        $this->assertFailuresContain('Stok Cıvata: sayım 2026-06-05 sayılan 90');
        $this->artisan('ledger:audit', ['--skip-reports' => true])->assertFailed();
    }

    public function test_untracked_items_are_ignored_by_the_stock_audit(): void
    {
        StockItem::factory()->untracked()->create(['name' => 'Hizmet']);

        $this->artisan('ledger:audit')
            ->expectsOutputToContain('Stok miktarı ve değeri hareketlerle tutarlı · kontrol 0 · uyumsuz 0')
            ->expectsOutputToContain('Cari, belgeler ve raporlar birebir tutuyor.')
            ->assertSuccessful();
    }

    private function purchase(StockItem $item, string $date, string $quantity, string $unitPrice): Invoice
    {
        return app(InvoiceService::class)->create([
            'direction' => 'purchase',
            'party_id' => Party::factory()->company()->create()->id,
            'invoice_no' => 'A-'.$date.'-'.$quantity,
            'issued_on' => $date,
            'lines' => [[
                'description' => $item->name,
                'stock_item_id' => $item->id,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'vat_rate' => '0',
            ]],
        ], $this->admin->id);
    }

    private function sale(StockItem $item, string $date, string $quantity, string $unitPrice): Invoice
    {
        return app(InvoiceService::class)->create([
            'direction' => 'sale',
            'party_id' => Party::factory()->company()->create()->id,
            'invoice_no' => 'S-'.$date.'-'.$quantity,
            'issued_on' => $date,
            'lines' => [[
                'description' => $item->name,
                'stock_item_id' => $item->id,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'vat_rate' => '0',
            ]],
        ], $this->admin->id);
    }

    private function assertFailuresContain(string ...$needles): void
    {
        $failures = collect(app(LedgerAudit::class)->run())
            ->firstWhere('check', 'Stok miktarı ve değeri hareketlerle tutarlı')['failures'];
        $blob = implode("\n", $failures);

        foreach ($needles as $needle) {
            $this->assertStringContainsString($needle, $blob, $blob);
        }
    }
}
