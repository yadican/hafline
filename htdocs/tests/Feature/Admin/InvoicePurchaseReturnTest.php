<?php

namespace Tests\Feature\Admin;

use App\Enums\InvoiceDirection;
use App\Enums\InvoicePaymentStatus;
use App\Models\Invoice;
use App\Models\Party;
use App\Models\StockCount;
use App\Models\StockItem;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoicePurchaseReturnTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Party $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->yonetici()->create();
        $this->supplier = Party::factory()->company()->create();
    }

    public function test_purchase_return_exits_stock_at_purchase_cost_not_average(): void
    {
        $item = StockItem::factory()->create(['name' => 'Çimento']);
        $this->purchase($item, 'A-1', '10', '100');
        $this->purchase($item, 'A-2', '10', '200');

        $this->actingAs($this->admin)
            ->post(route('admin.invoices.store'), $this->returnPayload('IR-1', $item, '5', '100'))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.invoices.index'));

        $return = Invoice::query()->where('invoice_no', 'IR-1')->sole();
        $this->assertSame(InvoiceDirection::PurchaseReturn, $return->direction);
        $this->assertSame(
            ['quantity' => 15.0, 'average_cost' => 166.6667, 'value' => 2500.0],
            app(StockService::class)->stock($item),
        );
    }

    public function test_purchase_return_debits_the_supplier_in_cari(): void
    {
        $item = StockItem::factory()->create();
        $this->purchase($item, 'A-CARI', '4', '50');

        $this->actingAs($this->admin)
            ->post(route('admin.invoices.store'), $this->returnPayload('IR-CARI', $item, '2', '50'))
            ->assertSessionHasNoErrors();

        $return = Invoice::query()->where('invoice_no', 'IR-CARI')->sole();
        $posted = $return->ledgerEntries()->sole();
        $this->assertSame(['100.00', '0.00'], [$posted->debit, $posted->credit]);
        $this->assertSame('Alış iadesi faturası IR-CARI', $posted->description);
        $this->artisan('ledger:audit')->expectsOutputToContain('Cari, belgeler ve raporlar birebir tutuyor.');
    }

    public function test_linked_purchase_return_reduces_open_purchase_remaining(): void
    {
        $item = StockItem::factory()->create();
        $this->purchase($item, 'A-OPEN', '10', '100');
        $purchase = Invoice::query()->where('invoice_no', 'A-OPEN')->sole();
        $this->assertSame([0.0, 1000.0, InvoicePaymentStatus::Unpaid], [
            $purchase->paidAmount(),
            $purchase->remainingAmount(),
            $purchase->paymentStatus(),
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.invoices.store'), [
                ...$this->returnPayload('IR-OPEN', $item, '3', '100'),
                'returns_invoice_id' => $purchase->id,
            ])
            ->assertSessionHasNoErrors();

        $purchase->refresh();
        unset($purchase->paid_amount, $purchase->returned_amount);
        $this->assertSame([300.0, 700.0, InvoicePaymentStatus::Partial], [
            $purchase->paidAmount(),
            $purchase->remainingAmount(),
            $purchase->paymentStatus(),
        ]);

        $return = Invoice::query()->where('invoice_no', 'IR-OPEN')->sole();
        $this->assertSame($purchase->id, $return->returns_invoice_id);
    }

    public function test_return_exceeding_purchase_remaining_is_rejected(): void
    {
        $item = StockItem::factory()->create(['name' => 'Demir']);
        $this->purchase($item, 'A-REM', '2', '100');
        $purchase = Invoice::query()->where('invoice_no', 'A-REM')->sole();

        $this->actingAs($this->admin)
            ->from(route('admin.invoices.create'))
            ->post(route('admin.invoices.store'), [
                ...$this->returnPayload('IR-OVER', $item, '2', '150'),
                'returns_invoice_id' => $purchase->id,
            ])
            ->assertRedirect(route('admin.invoices.create'))
            ->assertSessionHas('error');

        $this->assertSame(0, Invoice::query()->where('invoice_no', 'IR-OVER')->count());
        $purchase->refresh();
        unset($purchase->paid_amount, $purchase->returned_amount);
        $this->assertSame(200.0, $purchase->remainingAmount());
    }

    public function test_return_that_exceeds_stock_is_rejected_including_counts(): void
    {
        $item = StockItem::factory()->create(['name' => 'Kum']);
        $this->purchase($item, 'A-CNT', '10', '40');
        StockCount::factory()->create([
            'stock_item_id' => $item->id,
            'counted_on' => today()->toDateString(),
            'quantity' => 6,
            'difference' => -4,
        ]);

        $this->actingAs($this->admin)
            ->from(route('admin.invoices.create'))
            ->post(route('admin.invoices.store'), $this->returnPayload('IR-CNT', $item, '7', '40'))
            ->assertRedirect(route('admin.invoices.create'))
            ->assertSessionHas('error');

        $this->assertSame(0, Invoice::query()->where('invoice_no', 'IR-CNT')->count());
        $this->assertSame(6.0, app(StockService::class)->stock($item)['quantity']);
    }

    public function test_draft_return_does_not_affect_stock_cari_or_remaining_until_approved(): void
    {
        $item = StockItem::factory()->create();
        $this->purchase($item, 'A-DR', '8', '25');
        $purchase = Invoice::query()->where('invoice_no', 'A-DR')->sole();

        $this->actingAs($this->admin)
            ->post(route('admin.invoices.store'), [
                ...$this->returnPayload('IR-DR', $item, '3', '25'),
                'returns_invoice_id' => $purchase->id,
                'is_draft' => true,
            ])
            ->assertSessionHasNoErrors();

        $draft = Invoice::query()->where('invoice_no', 'IR-DR')->sole();
        $this->assertTrue($draft->is_draft);
        $this->assertSame(0, $draft->ledgerEntries()->count());
        $this->assertSame(8.0, app(StockService::class)->stock($item)['quantity']);
        $purchase->refresh();
        unset($purchase->paid_amount, $purchase->returned_amount);
        $this->assertSame(200.0, $purchase->remainingAmount());

        $this->actingAs($this->admin)
            ->post(route('admin.invoices.approve', $draft))
            ->assertSessionHas('success');

        $this->assertSame(5.0, app(StockService::class)->stock($item)['quantity']);
        $this->assertSame(1, $draft->fresh()->ledgerEntries()->count());
        $purchase->refresh();
        unset($purchase->paid_amount, $purchase->returned_amount);
        $this->assertSame([75.0, 125.0], [$purchase->paidAmount(), $purchase->remainingAmount()]);
    }

    /**
     * @return array<string, mixed>
     */
    private function returnPayload(string $no, StockItem $item, string $qty, string $price): array
    {
        return [
            'direction' => InvoiceDirection::PurchaseReturn->value,
            'party_id' => $this->supplier->id,
            'invoice_no' => $no,
            'issued_on' => today()->toDateString(),
            'is_draft' => false,
            'payment_status' => 'unpaid',
            'lines' => [[
                'description' => $item->name,
                'quantity' => $qty,
                'unit_price' => $price,
                'vat_rate' => '0',
                'stock_item_id' => $item->id,
            ]],
        ];
    }

    private function purchase(StockItem $item, string $no, string $qty, string $price): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.invoices.store'), [
                'direction' => 'purchase',
                'party_id' => $this->supplier->id,
                'invoice_no' => $no,
                'issued_on' => today()->toDateString(),
                'is_draft' => false,
                'payment_status' => 'unpaid',
                'lines' => [[
                    'description' => $item->name,
                    'quantity' => $qty,
                    'unit_price' => $price,
                    'vat_rate' => '0',
                    'stock_item_id' => $item->id,
                ]],
            ])
            ->assertSessionHasNoErrors();
    }
}
