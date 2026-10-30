<?php

namespace Tests\Feature;

use App\Enums\PartyRole;
use App\Exceptions\OperationRejectedException;
use App\Models\Party;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\Ledger\Ledger;
use App\Services\PartyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_computes_line_and_header_totals_with_and_without_vat(): void
    {
        $user = User::factory()->create();

        $invoice = app(InvoiceService::class)->create($this->data(lines: [
            ['description' => 'Çimento', 'quantity' => '2.5', 'unit_price' => '33.333', 'vat_rate' => '20'],
            ['description' => 'Nakliye', 'quantity' => '3', 'unit_price' => '100', 'vat_rate' => '0'],
        ]), $user->id);

        $lines = $invoice->lines()->get();
        $this->assertSame([['83.33', '16.67', '100.00', 0], ['300.00', '0.00', '300.00', 1]], $lines->map(fn ($line) => [$line->subtotal, $line->vat_amount, $line->total, $line->sort_order])->all());
        $this->assertSame(['383.33', '16.67', '400.00'], [$invoice->subtotal, $invoice->vat_amount, $invoice->total]);
        $this->assertSame($user->id, $invoice->created_by);
        $this->assertSame('400.00', app(Ledger::class)->live($invoice)->sole()->credit);
        $this->assertAuditPasses();
    }

    public function test_purchase_gives_the_party_the_supplier_role_and_sale_the_customer_role(): void
    {
        $supplier = Party::factory()->company()->create();
        $customer = Party::factory()->person()->create();

        app(InvoiceService::class)->create($this->data($supplier), null);
        app(InvoiceService::class)->create($this->data($customer, direction: 'sale'), null);

        $this->assertSame([PartyRole::Supplier], $supplier->fresh()->roles);
        $this->assertSame([PartyRole::Customer], $customer->fresh()->roles);
    }

    public function test_update_replaces_the_lines_and_reposts_the_new_total(): void
    {
        $invoice = app(InvoiceService::class)->create($this->data(), null);

        $updated = app(InvoiceService::class)->update($invoice, $this->data($invoice->party, lines: [
            ['description' => 'Kum', 'quantity' => '10', 'unit_price' => '50', 'vat_rate' => '20'],
        ]));

        $this->assertSame('Kum', $updated->lines()->sole()->description);
        $this->assertSame(['500.00', '100.00', '600.00'], [$updated->subtotal, $updated->vat_amount, $updated->total]);
        $this->assertSame('600.00', app(Ledger::class)->live($updated)->sole()->credit);
        $this->assertAuditPasses();
    }

    public function test_delete_reverses_the_posting(): void
    {
        $invoice = app(InvoiceService::class)->create($this->data(), null);

        app(InvoiceService::class)->delete($invoice);

        $this->assertSoftDeleted($invoice);
        $this->assertCount(0, app(Ledger::class)->live($invoice));
        $this->assertAuditPasses();
    }

    public function test_rejects_an_invoice_to_the_temporary_party(): void
    {
        $temporary = app(PartyService::class)->temporaryParty();

        $this->expectException(OperationRejectedException::class);

        app(InvoiceService::class)->create($this->data($temporary), null);
    }

    /**
     * @param  list<array<string, string>>|null  $lines
     * @return array<string, mixed>
     */
    private function data(?Party $party = null, string $direction = 'purchase', ?array $lines = null): array
    {
        return [
            'direction' => $direction,
            'party_id' => ($party ?? Party::factory()->company()->create())->id,
            'invoice_no' => 'A-1023',
            'issued_on' => '2026-03-10',
            'lines' => $lines ?? [['description' => 'Malzeme', 'quantity' => '1', 'unit_price' => '1000', 'vat_rate' => '0']],
        ];
    }

    private function assertAuditPasses(): void
    {
        $this->artisan('ledger:audit')
            ->expectsOutputToContain('Cari, belgeler ve raporlar birebir tutuyor.')
            ->assertSuccessful();
    }
}
