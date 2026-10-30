<?php

namespace Tests\Feature;

use App\Enums\Module;
use App\Models\Invoice;
use App\Models\LedgerEntry;
use App\Models\Party;
use App\Models\Site;
use App\Services\Ledger\InvoiceLedger;
use App\Services\Ledger\Ledger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceLedgerTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchase_invoice_credits_the_supplier_by_its_total_on_the_issue_day(): void
    {
        $site = Site::factory()->create();
        $invoice = Invoice::factory()->vat(20)->create(['subtotal' => 1000, 'invoice_no' => 'A-1023', 'issued_on' => '2026-03-10', 'site_id' => $site->id]);

        app(InvoiceLedger::class)->sync($invoice);

        $posted = app(Ledger::class)->live($invoice)->sole();
        $this->assertSame([$invoice->party_id, '2026-03-10', '0.00', '1200.00'], [$posted->party_id, $posted->occurred_on->toDateString(), $posted->debit, $posted->credit]);
        $this->assertSame([Module::General, $site->id], [$posted->module, $posted->site_id]);
        $this->assertSame('Alış faturası A-1023', $posted->description);
        $this->assertAuditPasses();
    }

    public function test_sale_invoice_debits_the_customer_by_its_total(): void
    {
        $invoice = Invoice::factory()->sale()->create(['subtotal' => 800]);

        app(InvoiceLedger::class)->sync($invoice);

        $posted = app(Ledger::class)->live($invoice)->sole();
        $this->assertSame(['800.00', '0.00'], [$posted->debit, $posted->credit]);
        $this->assertNull($posted->site_id);
        $this->assertAuditPasses();
    }

    public function test_editing_an_invoice_reverses_the_old_posting_and_posts_the_new_one(): void
    {
        $invoice = Invoice::factory()->create(['subtotal' => 1000]);
        $oldParty = $invoice->party_id;
        app(InvoiceLedger::class)->sync($invoice);

        $invoice->update(['party_id' => Party::factory()->create()->id, 'subtotal' => 1500, 'total' => 1500]);
        $invoice->lines()->sole()->update(['unit_price' => 1500, 'subtotal' => 1500, 'total' => 1500]);
        app(InvoiceLedger::class)->sync($invoice);

        $posted = app(Ledger::class)->live($invoice)->sole();
        $this->assertSame([$invoice->party_id, '1500.00'], [$posted->party_id, $posted->credit]);
        $this->assertSame(0.0, $this->balance($oldParty));
        $this->assertAuditPasses();
    }

    public function test_deleting_an_invoice_reverses_its_posting(): void
    {
        $invoice = Invoice::factory()->create(['subtotal' => 1000]);
        app(InvoiceLedger::class)->sync($invoice);

        $invoice->delete();
        app(InvoiceLedger::class)->void($invoice);

        $this->assertCount(0, app(Ledger::class)->live($invoice));
        $this->assertSame(2, $invoice->ledgerEntries()->count());
        $this->assertSame(0.0, $this->balance($invoice->party_id));
        $this->assertAuditPasses();
    }

    public function test_audit_fails_when_an_invoice_posting_differs_from_its_total(): void
    {
        $invoice = Invoice::factory()->create(['subtotal' => 1000]);
        app(InvoiceLedger::class)->sync($invoice);

        $invoice->ledgerEntries()->sole()->update(['credit' => 900]);

        $this->artisan('ledger:audit', ['--skip-reports' => true])
            ->expectsOutputToContain("invoice {$invoice->id}")
            ->assertFailed();
    }

    private function balance(string $partyId): float
    {
        return round((float) LedgerEntry::query()->where('party_id', $partyId)->sum('credit') - (float) LedgerEntry::query()->where('party_id', $partyId)->sum('debit'), 2);
    }

    private function assertAuditPasses(): void
    {
        $this->artisan('ledger:audit')
            ->expectsOutputToContain('Cari, belgeler ve raporlar birebir tutuyor.')
            ->assertSuccessful();
    }
}
