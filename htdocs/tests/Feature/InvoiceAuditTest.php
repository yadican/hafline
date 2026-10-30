<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Enums\InstrumentStatus;
use App\Models\Bank;
use App\Models\FinancialInstrument;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Party;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\Ledger\InvoiceLedger;
use App\Services\Ledger\LedgerAudit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InvoiceAuditTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->yonetici()->create();
        Bank::factory()->create(['type' => AccountType::Cash]);
    }

    public function test_audit_passes_for_an_invoice_with_lines_cari_and_a_payment_match(): void
    {
        $invoice = $this->invoice(1000);
        $this->pay($invoice, 400);

        $this->artisan('ledger:audit')
            ->expectsOutputToContain('Faturalar satırlar ve ödemelerle tutarlı · kontrol 1 · uyumsuz 0')
            ->expectsOutputToContain('Cari, belgeler ve raporlar birebir tutuyor.')
            ->assertSuccessful();
    }

    public function test_audit_fails_when_the_invoice_header_no_longer_matches_its_lines(): void
    {
        $invoice = $this->invoice(1000);
        app(InvoiceLedger::class)->sync($invoice);

        DB::table('invoices')->where('id', $invoice->id)->update(['subtotal' => 1500, 'total' => 1500]);

        $this->assertFailuresContain('Fatura A-1: başlık 1500');
        $this->artisan('ledger:audit', ['--skip-reports' => true])
            ->expectsOutputToContain('Faturalar satırlar ve ödemelerle tutarlı')
            ->assertFailed();
    }

    public function test_audit_fails_when_matches_exceed_the_invoice_total(): void
    {
        $invoice = $this->invoice(1000);
        $this->pay($invoice, 400);

        InvoicePayment::query()->sole()->update(['amount' => 1200]);

        $this->assertFailuresContain('Fatura A-1: eşleşmeler 1200', 'fatura toplamı 1000');
        $this->artisan('ledger:audit', ['--skip-reports' => true])->assertFailed();
    }

    public function test_audit_fails_when_live_matches_exceed_what_the_payment_pays(): void
    {
        $invoice = $this->invoice(1000);
        $this->pay($invoice, 400);

        InvoicePayment::query()->sole()->update(['amount' => 500]);

        $this->assertFailuresContain('Fatura A-1: eşleşmeler 500.00, ödeme tutarı 400.00');
        $this->artisan('ledger:audit', ['--skip-reports' => true])->assertFailed();
    }

    public function test_audit_fails_when_a_bounced_cheque_still_has_a_live_match(): void
    {
        $invoice = $this->invoice(1000);
        $this->pay($invoice, 1000, 'cheque');

        FinancialInstrument::query()->sole()->update(['status' => InstrumentStatus::Bounced]);

        $this->assertFailuresContain('Fatura A-1: eşleşmeler 1000.00, ödeme tutarı 0.00');
        $this->artisan('ledger:audit', ['--skip-reports' => true])->assertFailed();
    }

    public function test_soft_deleted_matches_are_ignored_by_the_audit(): void
    {
        $invoice = $this->invoice(1000);
        $this->pay($invoice, 400);

        $match = InvoicePayment::query()->sole();
        $match->update(['amount' => 5000]);
        $match->delete();

        $this->artisan('ledger:audit')
            ->expectsOutputToContain('Faturalar satırlar ve ödemelerle tutarlı · kontrol 1 · uyumsuz 0')
            ->expectsOutputToContain('Cari, belgeler ve raporlar birebir tutuyor.')
            ->assertSuccessful();
    }

    private function invoice(float $total): Invoice
    {
        return app(InvoiceService::class)->create([
            'direction' => 'purchase',
            'party_id' => Party::factory()->company()->create()->id,
            'invoice_no' => 'A-1',
            'issued_on' => '2026-10-01',
            'lines' => [['description' => 'Malzeme', 'quantity' => '1', 'unit_price' => (string) $total, 'vat_rate' => '0']],
        ], $this->admin->id);
    }

    private function pay(Invoice $invoice, float $amount, string $method = 'cash'): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.invoices.payments.store', $invoice), [
                'amount' => (string) $amount,
                'occurred_on' => '2026-10-10',
                'payment_method' => $method,
                'bank_id' => Bank::query()->sole()->id,
                ...($method === 'cheque' ? ['cheque_no' => 'Ç-1', 'due_on' => '2026-12-10'] : []),
            ])
            ->assertSessionHasNoErrors();
    }

    private function assertFailuresContain(string ...$needles): void
    {
        $failures = collect(app(LedgerAudit::class)->run())
            ->firstWhere('check', 'Faturalar satırlar ve ödemelerle tutarlı')['failures'];
        $blob = implode("\n", $failures);

        foreach ($needles as $needle) {
            $this->assertStringContainsString($needle, $blob, $blob);
        }
    }
}
