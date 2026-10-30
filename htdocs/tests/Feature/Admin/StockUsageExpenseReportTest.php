<?php

namespace Tests\Feature\Admin;

use App\Enums\PaymentMethod;
use App\Models\CashCategory;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Party;
use App\Models\Site;
use App\Models\SiteTransaction;
use App\Models\StockItem;
use App\Models\StockUsage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockUsageExpenseReportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->yonetici()->create();
        $this->site = Site::factory()->create(['name' => 'BAKIRKÖY']);
    }

    public function test_site_expense_report_includes_stock_usage_cost_as_debit(): void
    {
        $item = StockItem::factory()->create(['name' => 'Çimento']);
        $category = CashCategory::factory()->create(['name' => 'Malzeme']);

        $this->purchase($item, '2026-08-01', '100', '40');
        $this->purchase($item, '2026-08-05', '100', '46');

        SiteTransaction::query()->create([
            'site_id' => $this->site->id,
            'payment_method' => PaymentMethod::Cash,
            'debit' => 0,
            'credit' => 1000,
            'occurred_on' => '2026-08-20',
            'description' => 'Eski hakediş',
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.stock-usages.store'), [
                'stock_item_id' => $item->id,
                'quantity' => '50',
                'used_on' => '2026-09-05',
                'site_id' => $this->site->id,
                'cash_category_id' => $category->id,
                'description' => 'Şantiye stok kullanımı',
            ])
            ->assertSessionHasNoErrors();

        SiteTransaction::query()->create([
            'site_id' => $this->site->id,
            'payment_method' => PaymentMethod::BankTransfer,
            'debit' => 0,
            'credit' => 500,
            'occurred_on' => '2026-09-10',
            'description' => 'Hakediş',
        ]);

        $report = $this->actingAs($this->admin)
            ->get(route('admin.reports.site-expense', [
                'period' => 'month',
                'year' => 2026,
                'month' => 9,
                'site' => $this->site->id,
            ]))
            ->assertOk()
            ->assertSee('Şantiye stok kullanımı', false)
            ->assertSee('2.150,00', false)
            ->viewData('report');

        $this->assertSame(-1000.0, $report['opening']);
        $this->assertCount(2, $report['lines']);
        $this->assertSame(2150.0, $report['lines'][0]['debit']);
        $this->assertSame(0.0, $report['lines'][0]['credit']);
        $this->assertSame(2150.0, $report['total_debit']);
        $this->assertSame(500.0, $report['total_credit']);
        $this->assertSame(650.0, $report['closing']);
    }

    public function test_soft_deleted_usage_is_excluded_from_site_expense_report(): void
    {
        $item = StockItem::factory()->create();
        $category = CashCategory::factory()->create();
        $this->purchase($item, '2026-09-01', '10', '100');

        $this->actingAs($this->admin)
            ->post(route('admin.stock-usages.store'), [
                'stock_item_id' => $item->id,
                'quantity' => '2',
                'used_on' => '2026-09-05',
                'site_id' => $this->site->id,
                'cash_category_id' => $category->id,
            ])
            ->assertSessionHasNoErrors();

        $usage = StockUsage::query()->sole();
        $usage->delete();

        $report = $this->actingAs($this->admin)
            ->get(route('admin.reports.site-expense', [
                'period' => 'month',
                'year' => 2026,
                'month' => 9,
                'site' => $this->site->id,
            ]))
            ->viewData('report');

        $this->assertCount(0, $report['lines']);
        $this->assertSame(0.0, $report['total_debit']);
    }

    private function purchase(StockItem $item, string $date, string $quantity, string $unitPrice): void
    {
        $subtotal = round((float) $quantity * (float) $unitPrice, 2);
        $invoice = Invoice::factory()->create([
            'party_id' => Party::factory()->company(),
            'issued_on' => $date,
            'subtotal' => $subtotal,
            'vat_amount' => 0,
            'is_draft' => false,
        ]);
        $invoice->lines()->delete();
        InvoiceLine::factory()->create([
            'invoice_id' => $invoice->id,
            'stock_item_id' => $item->id,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'subtotal' => $subtotal,
            'vat_amount' => 0,
            'total' => $subtotal,
        ]);
    }
}
