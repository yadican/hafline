<?php

namespace Tests\Feature\Admin;

use App\Models\Invoice;
use App\Models\Party;
use App\Models\StockItem;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceSaleStockGuardTest extends TestCase
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

    public function test_sale_that_exceeds_stock_on_sale_day_is_rejected(): void
    {
        $item = StockItem::factory()->create(['name' => 'Çimento']);
        $this->purchase($item, 'A-GUARD-1', '2026-03-01', '10', '40');

        $this->actingAs($this->admin)
            ->from(route('admin.invoices.create'))
            ->post(route('admin.invoices.store'), $this->salePayload('S-OVER', '2026-03-10', $item, '12', '60'))
            ->assertRedirect(route('admin.invoices.create'))
            ->assertSessionHas('error', 'Çimento stokunda yeterli miktar yok (10.03.2026 tarihinde kalan 10 adet).');

        $this->assertSame(0, Invoice::query()->where('invoice_no', 'S-OVER')->count());
        $this->assertSame(
            ['quantity' => 10.0, 'average_cost' => 40.0, 'value' => 400.0],
            app(StockService::class)->stock($item),
        );
    }

    public function test_sale_that_would_leave_later_days_negative_is_rejected(): void
    {
        $item = StockItem::factory()->create(['name' => 'Demir']);
        $this->purchase($item, 'A-LATER-1', '2026-03-01', '10', '50');
        $this->sale($item, 'S-LATER-OUT', '2026-03-20', '8', '80');

        $this->actingAs($this->admin)
            ->from(route('admin.invoices.create'))
            ->post(route('admin.invoices.store'), $this->salePayload('S-LATER-IN', '2026-03-10', $item, '5', '70'))
            ->assertRedirect(route('admin.invoices.create'))
            ->assertSessionHas('error', 'Demir satışı sonraki stok hareketlerini eksiye düşürür (10.03.2026 tarihinde kalan 10 adet).');

        $this->assertSame(0, Invoice::query()->where('invoice_no', 'S-LATER-IN')->count());
        $this->assertSame(
            ['quantity' => 2.0, 'average_cost' => 50.0, 'value' => 100.0],
            app(StockService::class)->stock($item),
        );
    }

    public function test_under_cost_sale_saves_with_info_warning(): void
    {
        $item = StockItem::factory()->create(['name' => 'Kum']);
        $this->purchase($item, 'A-COST-1', '2026-03-01', '20', '100');

        $this->actingAs($this->admin)
            ->post(route('admin.invoices.store'), $this->salePayload('S-COST-1', '2026-03-05', $item, '5', '80'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success')
            ->assertSessionHas('info', 'Maliyetin altında satış: Kum (maliyet 100 TL, satış 80 TL).')
            ->assertRedirect(route('admin.invoices.index'));

        $sale = Invoice::query()->where('invoice_no', 'S-COST-1')->sole();
        $this->assertSame('100.0000', $sale->lines()->sole()->unit_cost);
        $this->assertSame(
            ['quantity' => 15.0, 'average_cost' => 100.0, 'value' => 1500.0],
            app(StockService::class)->stock($item),
        );
    }

    public function test_draft_sale_over_stock_is_rejected_only_on_approve(): void
    {
        $item = StockItem::factory()->create(['name' => 'Çakıl']);
        $this->purchase($item, 'A-DRAFT-G', '2026-03-01', '4', '25');

        $this->actingAs($this->admin)
            ->post(route('admin.invoices.store'), [
                ...$this->salePayload('S-DRAFT-G', '2026-03-05', $item, '10', '40'),
                'is_draft' => true,
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $invoice = Invoice::query()->where('invoice_no', 'S-DRAFT-G')->sole();
        $this->assertTrue($invoice->isDraft());
        $this->assertSame(
            ['quantity' => 4.0, 'average_cost' => 25.0, 'value' => 100.0],
            app(StockService::class)->stock($item),
        );

        $this->actingAs($this->admin)
            ->from(route('admin.invoices.show', $invoice))
            ->post(route('admin.invoices.approve', $invoice))
            ->assertRedirect(route('admin.invoices.show', $invoice))
            ->assertSessionHas('error', 'Çakıl stokunda yeterli miktar yok (05.03.2026 tarihinde kalan 4 adet).');

        $invoice->refresh();
        $this->assertTrue($invoice->isDraft());
        $this->assertSame(
            ['quantity' => 4.0, 'average_cost' => 25.0, 'value' => 100.0],
            app(StockService::class)->stock($item),
        );
    }

    public function test_updating_live_sale_within_stock_after_excluding_self_succeeds(): void
    {
        $item = StockItem::factory()->create();
        $this->purchase($item, 'A-EDIT-1', '2026-03-01', '10', '30');
        $this->sale($item, 'S-EDIT-1', '2026-03-05', '6', '50');

        $invoice = Invoice::query()->where('invoice_no', 'S-EDIT-1')->sole();

        $payload = $this->salePayload('S-EDIT-1', '2026-03-05', $item, '9', '55');
        unset($payload['is_draft'], $payload['payment_status']);

        $this->actingAs($this->admin)
            ->put(route('admin.invoices.update', $invoice), $payload)
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $this->assertSame(
            ['quantity' => 1.0, 'average_cost' => 30.0, 'value' => 30.0],
            app(StockService::class)->stock($item),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function salePayload(string $no, string $date, StockItem $item, string $quantity, string $unitPrice): array
    {
        return [
            'direction' => 'sale',
            'party_id' => $this->customer->id,
            'invoice_no' => $no,
            'issued_on' => $date,
            'is_draft' => false,
            'payment_status' => 'unpaid',
            'lines' => [[
                'description' => $item->name,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'vat_rate' => '0',
                'stock_item_id' => $item->id,
            ]],
        ];
    }

    private function purchase(StockItem $item, string $no, string $date, string $quantity, string $unitPrice): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.invoices.store'), [
                'direction' => 'purchase',
                'party_id' => $this->supplier->id,
                'invoice_no' => $no,
                'issued_on' => $date,
                'is_draft' => false,
                'payment_status' => 'unpaid',
                'lines' => [[
                    'description' => $item->name,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'vat_rate' => '0',
                    'stock_item_id' => $item->id,
                ]],
            ])
            ->assertSessionHasNoErrors();
    }

    private function sale(StockItem $item, string $no, string $date, string $quantity, string $unitPrice): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.invoices.store'), $this->salePayload($no, $date, $item, $quantity, $unitPrice))
            ->assertSessionHasNoErrors();
    }
}
