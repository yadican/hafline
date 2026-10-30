<?php

namespace Tests\Feature\Admin;

use App\Enums\AccountType;
use App\Enums\InvoicePaymentStatus;
use App\Exceptions\OperationRejectedException;
use App\Models\Bank;
use App\Models\CompanyTransaction;
use App\Models\FinancialInstrument;
use App\Models\FinancialInstrumentMovement;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Party;
use App\Models\User;
use App\Services\InvoicePaymentService;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class InvoiceBouncedChequeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Party $party;

    private Bank $cashBox;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->yonetici()->create();
        $this->party = Party::factory()->company()->create();
        $this->cashBox = Bank::factory()->create(['type' => AccountType::Cash]);
    }

    public function test_a_received_cheque_returned_unpaid_reopens_the_sale_invoice_until_taken_back(): void
    {
        $invoice = $this->invoice('sale', 'S-1', 1000);
        $this->payWith($invoice, 'cheque', 1000)->assertSessionHasNoErrors();
        $cheque = FinancialInstrument::query()->sole();

        $this->bounce($cheque)->assertSessionHas('success');

        $this->assertSame([0.0, 1000.0, InvoicePaymentStatus::Unpaid], $this->state($invoice));
        $this->assertSame(FinancialInstrumentMovement::query()->sole()->id, InvoicePayment::onlyTrashed()->sole()->released_by_movement_id);
        $this->assertAuditPasses();

        $this->undo($cheque)->assertSessionHas('success');

        $this->assertSame([1000.0, 0.0, InvoicePaymentStatus::Paid], $this->state($invoice));
        $this->assertNull(InvoicePayment::query()->sole()->released_by_movement_id);
        $this->assertAuditPasses();
    }

    public function test_a_given_cheque_returned_unpaid_reopens_the_purchase_invoice_until_taken_back(): void
    {
        $invoice = $this->invoice('purchase', 'A-1', 1000);
        $this->payWith($invoice, 'cheque', 400)->assertSessionHasNoErrors();
        $cheque = FinancialInstrument::query()->sole();

        $this->bounce($cheque)->assertSessionHas('success');

        $this->assertSame([0.0, 1000.0, InvoicePaymentStatus::Unpaid], $this->state($invoice));
        $this->assertAuditPasses();

        $this->undo($cheque)->assertSessionHas('success');

        $this->assertSame([400.0, 600.0, InvoicePaymentStatus::Partial], $this->state($invoice));
        $this->assertAuditPasses();
    }

    public function test_a_bounced_cheque_cannot_be_matched_again(): void
    {
        $invoice = $this->invoice('purchase', 'A-1', 1000);
        $this->payWith($invoice, 'cheque', 1000)->assertSessionHasNoErrors();
        $this->bounce(FinancialInstrument::query()->sole())->assertSessionHas('success');

        $this->expectException(OperationRejectedException::class);

        app(InvoicePaymentService::class)->match($invoice, CompanyTransaction::query()->sole());
    }

    public function test_taking_back_does_not_reach_past_what_other_payments_settled_meanwhile(): void
    {
        $first = $this->invoice('purchase', 'A-1', 600);
        $second = $this->invoice('purchase', 'A-2', 400);
        $cheque = $this->chequeSplitOver($first, $second);

        $this->bounce($cheque)->assertSessionHas('success');
        $this->payWith($first, 'cash', 600)->assertSessionHasNoErrors();
        $this->payWith($second, 'cash', 100)->assertSessionHasNoErrors();

        $this->undo($cheque)->assertSessionHas('success');

        $this->assertSame([600.0, 0.0, InvoicePaymentStatus::Paid], $this->state($first));
        $this->assertSame([400.0, 0.0, InvoicePaymentStatus::Paid], $this->state($second));
        $this->assertSame('300.00', $this->chequeMatch($second)->amount);
        $this->assertSame(0, $first->payments()->whereMorphedTo('payment', $cheque->source)->count());
        $this->assertAuditPasses();
    }

    public function test_taking_back_brings_back_only_the_matches_the_return_released(): void
    {
        $first = $this->invoice('purchase', 'A-1', 600);
        $second = $this->invoice('purchase', 'A-2', 400);
        $cheque = $this->chequeSplitOver($first, $second);
        app(InvoicePaymentService::class)->unmatch($this->chequeMatch($second));

        $this->bounce($cheque)->assertSessionHas('success');
        $this->undo($cheque)->assertSessionHas('success');

        $this->assertSame([600.0, 0.0, InvoicePaymentStatus::Paid], $this->state($first));
        $this->assertSame([0.0, 400.0, InvoicePaymentStatus::Unpaid], $this->state($second));
        $this->assertAuditPasses();
    }

    private function invoice(string $direction, string $number, float $total): Invoice
    {
        return app(InvoiceService::class)->create([
            'direction' => $direction,
            'party_id' => $this->party->id,
            'invoice_no' => $number,
            'issued_on' => '2026-10-01',
            'lines' => [['description' => 'Malzeme', 'quantity' => '1', 'unit_price' => (string) $total, 'vat_rate' => '0']],
        ], $this->admin->id);
    }

    private function payWith(Invoice $invoice, string $method, float $amount): TestResponse
    {
        return $this->actingAs($this->admin)->post(route('admin.invoices.payments.store', $invoice), [
            'amount' => (string) $amount,
            'occurred_on' => '2026-10-10',
            'payment_method' => $method,
            'bank_id' => $this->cashBox->id,
            ...($method === 'cheque' ? ['cheque_no' => 'Ç-55', 'due_on' => '2026-12-10'] : []),
        ]);
    }

    /**
     * A given cheque of 1000 paid to the supplier, matched first to one invoice, then the next.
     */
    private function chequeSplitOver(Invoice $first, Invoice $second): FinancialInstrument
    {
        $this->actingAs($this->admin)->post(route('admin.cari-entries.store'), [
            'kind' => 'supplier',
            'direction' => 'debit',
            'party_id' => $this->party->id,
            'occurred_on' => '2026-10-10',
            'amount' => '1000',
            'payment_method' => 'cheque',
            'cheque_no' => 'Ç-56',
            'due_on' => '2026-12-10',
        ])->assertSessionHasNoErrors();
        $entry = CompanyTransaction::query()->sole();
        $matches = app(InvoicePaymentService::class);
        $this->travelTo('2026-10-30 09:00:00');
        $matches->match($first, $entry);
        $this->travelTo('2026-10-30 10:00:00');
        $matches->match($second, $entry);

        return FinancialInstrument::query()->sole();
    }

    private function chequeMatch(Invoice $invoice): InvoicePayment
    {
        return $invoice->payments()->whereMorphedTo('payment', FinancialInstrument::query()->sole()->source)->sole();
    }

    private function bounce(FinancialInstrument $instrument): TestResponse
    {
        return $this->actingAs($this->admin)
            ->post(route('admin.financial-instruments.movements.store', $instrument), ['type' => 'bounce', 'occurred_on' => '2026-10-20']);
    }

    private function undo(FinancialInstrument $instrument): TestResponse
    {
        return $this->actingAs($this->admin)->delete(route('admin.financial-instruments.movements.undo', $instrument));
    }

    /**
     * @return array{0: float, 1: float, 2: InvoicePaymentStatus}
     */
    private function state(Invoice $invoice): array
    {
        $invoice = $invoice->fresh();

        return [$invoice->paidAmount(), $invoice->remainingAmount(), $invoice->paymentStatus()];
    }

    private function assertAuditPasses(): void
    {
        $this->artisan('ledger:audit')
            ->expectsOutputToContain('Cari, belgeler ve raporlar birebir tutuyor.')
            ->assertSuccessful();
    }
}
