<?php

namespace Tests\Feature\Admin;

use App\Enums\InvoiceDirection;
use App\Enums\InvoicePaymentStatus;
use App\Models\Invoice;
use App\Models\Party;
use App\Models\StockItem;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceSaleReturnTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Party $supplier;

    private Party $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->yonetici()->create();
        $this->supplier = Party::factory()->company()->create();
        $this->customer = Party::factory()->company()->create();
    }

    public function test_sale_return_enters_stock_at_frozen_sale_cost_not_average(): void
    {
        $item = StockItem::factory()->create(['name' => 'Çimento']);
        $this->purchase($item, 'A-1', '10', '100', '2026-03-01');
        $this->sale($item, 'S-1', '5', '150', '2026-03-10');
        $this->purchase($item, 'A-2', '10', '200', '2026-03-20');

        $this->assertSame(
            ['quantity' => 15.0, 'average_cost' => 166.6667, 'value' => 2500.0],
            app(StockService::class)->stock($item),
        );
        $this->assertSame('100.0000', Invoice::query()->where('invoice_no', 'S-1')->sole()->lines()->sole()->unit_cost);

        $this->actingAs($this->admin)
            ->post(route('admin.invoices.store'), $this->returnPayload('SR-1', $item, '3', '150', '2026-03-25'))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.invoices.index'));

        $return = Invoice::query()->where('invoice_no', 'SR-1')->sole();
        $this->assertSame(InvoiceDirection::SaleReturn, $return->direction);
        $this->assertSame('100.0000', $return->lines()->sole()->unit_cost);
        $this->assertSame(
            ['quantity' => 18.0, 'average_cost' => 155.5556, 'value' => 2800.0],
            app(StockService::class)->stock($item),
        );
    }

    public function test_sale_return_credits_the_customer_in_cari(): void
    {
        $item = StockItem::factory()->create();
        $this->purchase($item, 'A-CARI', '4', '50');
        $this->sale($item, 'S-CARI', '2', '80');

        $this->actingAs($this->admin)
            ->post(route('admin.invoices.store'), $this->returnPayload('SR-CARI', $item, '1', '80'))
            ->assertSessionHasNoErrors();

        $return = Invoice::query()->where('invoice_no', 'SR-CARI')->sole();
        $posted = $return->ledgerEntries()->sole();
        $this->assertSame(['0.00', '80.00'], [$posted->debit, $posted->credit]);
        $this->assertSame('Satış iadesi faturası SR-CARI', $posted->description);
        $this->artisan('ledger:audit')->expectsOutputToContain('Cari, belgeler ve raporlar birebir tutuyor.');
    }

    public function test_linked_sale_return_reduces_open_sale_remaining(): void
    {
        $item = StockItem::factory()->create();
        $this->purchase($item, 'A-OPEN', '10', '100');
        $this->sale($item, 'S-OPEN', '10', '120');
        $sale = Invoice::query()->where('invoice_no', 'S-OPEN')->sole();
        $this->assertSame([0.0, 1200.0, InvoicePaymentStatus::Unpaid], [
            $sale->paidAmount(),
            $sale->remainingAmount(),
            $sale->paymentStatus(),
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.invoices.store'), [
                ...$this->returnPayload('SR-OPEN', $item, '3', '120'),
                'returns_invoice_id' => $sale->id,
            ])
            ->assertSessionHasNoErrors();

        $sale->refresh();
        unset($sale->paid_amount, $sale->returned_amount);
        $this->assertSame([360.0, 840.0, InvoicePaymentStatus::Partial], [
            $sale->paidAmount(),
            $sale->remainingAmount(),
            $sale->paymentStatus(),
        ]);

        $return = Invoice::query()->where('invoice_no', 'SR-OPEN')->sole();
        $this->assertSame($sale->id, $return->returns_invoice_id);
        $this->assertSame('100.0000', $return->lines()->sole()->unit_cost);
    }

    public function test_return_exceeding_sale_remaining_is_rejected(): void
    {
        $item = StockItem::factory()->create(['name' => 'Demir']);
        $this->purchase($item, 'A-REM', '2', '100');
        $this->sale($item, 'S-REM', '2', '100');
        $sale = Invoice::query()->where('invoice_no', 'S-REM')->sole();

        $this->actingAs($this->admin)
            ->from(route('admin.invoices.create'))
            ->post(route('admin.invoices.store'), [
                ...$this->returnPayload('SR-OVER', $item, '2', '150'),
                'returns_invoice_id' => $sale->id,
            ])
            ->assertRedirect(route('admin.invoices.create'))
            ->assertSessionHas('error');

        $this->assertSame(0, Invoice::query()->where('invoice_no', 'SR-OVER')->count());
        $sale->refresh();
        unset($sale->paid_amount, $sale->returned_amount);
        $this->assertSame(200.0, $sale->remainingAmount());
    }

    public function test_draft_return_does_not_affect_stock_cari_or_remaining_until_approved(): void
    {
        $item = StockItem::factory()->create();
        $this->purchase($item, 'A-DR', '8', '25');
        $this->sale($item, 'S-DR', '4', '40');
        $sale = Invoice::query()->where('invoice_no', 'S-DR')->sole();

        $this->actingAs($this->admin)
            ->post(route('admin.invoices.store'), [
                ...$this->returnPayload('SR-DR', $item, '2', '40'),
                'returns_invoice_id' => $sale->id,
                'is_draft' => true,
            ])
            ->assertSessionHasNoErrors();

        $draft = Invoice::query()->where('invoice_no', 'SR-DR')->sole();
        $this->assertTrue($draft->is_draft);
        $this->assertSame(0, $draft->ledgerEntries()->count());
        $this->assertNull($draft->lines()->sole()->unit_cost);
        $this->assertSame(4.0, app(StockService::class)->stock($item)['quantity']);
        $sale->refresh();
        unset($sale->paid_amount, $sale->returned_amount);
        $this->assertSame(160.0, $sale->remainingAmount());

        $this->actingAs($this->admin)
            ->post(route('admin.invoices.approve', $draft))
            ->assertSessionHas('success');

        $this->assertSame(6.0, app(StockService::class)->stock($item)['quantity']);
        $this->assertSame(1, $draft->fresh()->ledgerEntries()->count());
        $this->assertSame('25.0000', $draft->fresh()->lines()->sole()->unit_cost);
        $sale->refresh();
        unset($sale->paid_amount, $sale->returned_amount);
        $this->assertSame([80.0, 80.0], [$sale->paidAmount(), $sale->remainingAmount()]);
    }

    /**
     * @return array<string, mixed>
     */
    private function returnPayload(string $no, StockItem $item, string $qty, string $price, ?string $on = null): array
    {
        return [
            'direction' => InvoiceDirection::SaleReturn->value,
            'party_id' => $this->customer->id,
            'invoice_no' => $no,
            'issued_on' => $on ?? today()->toDateString(),
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

    private function purchase(StockItem $item, string $no, string $qty, string $price, ?string $on = null): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.invoices.store'), [
                'direction' => 'purchase',
                'party_id' => $this->supplier->id,
                'invoice_no' => $no,
                'issued_on' => $on ?? today()->toDateString(),
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

    private function sale(StockItem $item, string $no, string $qty, string $price, ?string $on = null): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.invoices.store'), [
                'direction' => 'sale',
                'party_id' => $this->customer->id,
                'invoice_no' => $no,
                'issued_on' => $on ?? today()->toDateString(),
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
