<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Enums\InvoicePaymentStatus;
use App\Enums\PartyRole;
use App\Exceptions\OperationRejectedException;
use App\Models\Bank;
use App\Models\CompanyTransaction;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Party;
use App\Models\User;
use App\Services\CariEntryService;
use App\Services\InvoicePaymentService;
use App\Services\InvoiceService;
use Closure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoicePaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    private Party $supplier;

    private Bank $cashBox;

    protected function setUp(): void
    {
        parent::setUp();

        $this->supplier = Party::factory()->company()->withRole(PartyRole::Supplier)->create();
        $this->cashBox = Bank::factory()->create(['type' => AccountType::Cash]);
    }

    public function test_paid_remaining_and_status_follow_the_matched_payments(): void
    {
        $user = User::factory()->create();
        $invoice = $this->invoice(1000);

        $this->assertSame([0.0, 1000.0, InvoicePaymentStatus::Unpaid], $this->state($invoice));

        $match = $this->payments()->match($invoice, $this->payment(400), userId: $user->id);

        $this->assertSame(['400.00', $user->id], [$match->amount, $match->created_by]);
        $this->assertSame([400.0, 600.0, InvoicePaymentStatus::Partial], $this->state($invoice));

        $this->payments()->match($invoice, $this->payment(600));

        $this->assertSame([1000.0, 0.0, InvoicePaymentStatus::Paid], $this->state($invoice->fresh()));
        $this->assertAuditPasses();
    }

    public function test_without_an_amount_the_smaller_of_remaining_and_unmatched_is_matched(): void
    {
        $payment = $this->payment(1500);
        $first = $this->invoice(1000, 'A-1');
        $second = $this->invoice(800, 'A-2');

        $this->assertSame('1000.00', $this->payments()->match($first, $payment)->amount);
        $this->assertSame(500.0, $this->payments()->unmatchedAmount($payment, $second));
        $this->assertSame('500.00', $this->payments()->match($second, $payment)->amount);

        $this->assertSame(InvoicePaymentStatus::Paid, $first->paymentStatus());
        $this->assertSame([500.0, 300.0, InvoicePaymentStatus::Partial], $this->state($second));
        $this->assertSame(0.0, $this->payments()->unmatchedAmount($payment, $second));
    }

    public function test_a_match_cannot_exceed_the_invoice_remaining(): void
    {
        $invoice = $this->invoice(1000);
        $this->payments()->match($invoice, $this->payment(700));

        $this->assertRejected(fn () => $this->payments()->match($invoice, $this->payment(500), 301), 'faturanın kalanını (300,00 TL) aşamaz');
        $this->assertSame('300.00', $this->payments()->match($invoice, $this->payment(500))->amount);
        $this->assertRejected(fn () => $this->payments()->match($invoice, $this->payment(100)), 'Faturanın kalanı yok.');
    }

    public function test_a_match_cannot_exceed_the_unmatched_part_of_the_payment(): void
    {
        $payment = $this->payment(500);
        $this->payments()->match($this->invoice(1000, 'A-1'), $payment, 300);
        $second = $this->invoice(1000, 'A-2');

        $this->assertRejected(fn () => $this->payments()->match($second, $payment, 201), 'bağlanmamış kısmını (200,00 TL) aşamaz');
        $this->assertRejected(fn () => $this->payments()->match($second, $payment, 0), 'sıfırdan büyük');

        $this->payments()->match($second, $payment, 200);

        $this->assertRejected(fn () => $this->payments()->match($this->invoice(100, 'A-3'), $payment), 'tamamen başka faturalara bağlanmış');
        $this->assertSame(2, InvoicePayment::query()->count());
    }

    public function test_matching_the_same_payment_again_adds_to_its_match(): void
    {
        $invoice = $this->invoice(1000);
        $payment = $this->payment(600);

        $this->payments()->match($invoice, $payment, 250);
        $match = $this->payments()->match($invoice, $payment, 350);

        $this->assertSame('600.00', $match->amount);
        $this->assertSame(1, InvoicePayment::query()->count());
        $this->assertSame(600.0, $invoice->paidAmount());
    }

    public function test_only_a_payment_on_the_invoice_party_and_side_settles_it(): void
    {
        $invoice = $this->invoice(1000);
        $otherSupplier = Party::factory()->company()->withRole(PartyRole::Supplier)->create();

        $this->assertRejected(fn () => $this->payments()->match($invoice, $this->payment(500, party: $otherSupplier)), 'carisine yapılmış bir ödeme değil');
        $this->assertRejected(fn () => $this->payments()->match($invoice, $this->payment(500, direction: 'credit')), 'carisine yapılmış bir ödeme değil');
        $this->assertRejected(fn () => $this->payments()->match($invoice, $this->unpaidEntry(500)), 'carisine yapılmış bir ödeme değil');

        $payment = $this->payment(500);
        $payment->delete();

        $this->assertRejected(fn () => $this->payments()->match($invoice, $payment), 'Ödeme kaydı bulunamadı.');
        $this->assertSame(0, InvoicePayment::query()->count());
    }

    public function test_a_sale_invoice_is_settled_by_a_collection_from_the_customer(): void
    {
        $customer = Party::factory()->company()->withRole(PartyRole::Customer)->create();
        $invoice = $this->invoice(900, party: $customer, direction: 'sale');

        $this->assertRejected(fn () => $this->payments()->match($invoice, $this->payment(900, party: $customer, kind: 'customer')), 'carisine yapılmış bir tahsilat değil');

        $this->payments()->match($invoice, $this->payment(900, party: $customer, kind: 'customer', direction: 'credit'));

        $this->assertSame(InvoicePaymentStatus::Paid, $invoice->paymentStatus());
        $this->assertSame('Tahsil edildi', $invoice->paymentStatus()->label($invoice->direction));
    }

    public function test_unmatch_gives_the_amount_back_to_the_invoice_and_the_payment(): void
    {
        $invoice = $this->invoice(1000);
        $payment = $this->payment(1000);
        $match = $this->payments()->match($invoice, $payment);

        $this->payments()->unmatch($match);

        $this->assertSoftDeleted($match);
        $this->assertSame([0.0, 1000.0, InvoicePaymentStatus::Unpaid], $this->state($invoice->fresh()));
        $this->assertSame(1000.0, $this->payments()->unmatchedAmount($payment, $invoice));
    }

    public function test_a_paid_invoice_is_not_overdue(): void
    {
        $this->travelTo('2026-10-30');
        $invoice = $this->invoice(1000, due: '2026-10-15');
        $this->payments()->match($invoice, $this->payment(999));

        $this->assertTrue($invoice->isOverdue());

        $this->payments()->match($invoice, $this->payment(1));

        $this->assertFalse($invoice->fresh()->isOverdue());
    }

    public function test_an_invoice_with_payments_keeps_its_party_direction_and_matched_total(): void
    {
        $invoice = $this->invoice(1000);
        $this->payments()->match($invoice, $this->payment(600));
        $otherSupplier = Party::factory()->company()->create();

        $this->assertRejected(fn () => $this->invoices()->update($invoice, $this->data(599)), 'bağlanan ödemelerin toplamından (600,00 TL) az olamaz');
        $this->assertRejected(fn () => $this->invoices()->update($invoice, $this->data(1000, party: $otherSupplier)), 'carisi veya yönü değiştirilemez');
        $this->assertRejected(fn () => $this->invoices()->update($invoice, $this->data(1000, direction: 'sale')), 'carisi veya yönü değiştirilemez');
        $this->assertSame('1000.00', $invoice->fresh()->total);

        $updated = $this->invoices()->update($invoice, $this->data(600));

        $this->assertSame(InvoicePaymentStatus::Paid, $updated->fresh()->paymentStatus());
        $this->assertAuditPasses();
    }

    /**
     * @return array{0: float, 1: float, 2: InvoicePaymentStatus}
     */
    private function state(Invoice $invoice): array
    {
        return [$invoice->paidAmount(), $invoice->remainingAmount(), $invoice->paymentStatus()];
    }

    private function invoice(float $total, string $number = 'A-1023', ?Party $party = null, string $direction = 'purchase', ?string $due = null): Invoice
    {
        return $this->invoices()->create($this->data($total, $number, $party, $direction, $due), null);
    }

    /**
     * @return array<string, mixed>
     */
    private function data(float $total, string $number = 'A-1023', ?Party $party = null, string $direction = 'purchase', ?string $due = null): array
    {
        return [
            'direction' => $direction,
            'party_id' => ($party ?? $this->supplier)->id,
            'invoice_no' => $number,
            'issued_on' => '2026-10-01',
            'due_on' => $due,
            'lines' => [['description' => 'Malzeme', 'quantity' => '1', 'unit_price' => (string) $total, 'vat_rate' => '0']],
        ];
    }

    /**
     * A cash entry on the party's cari: a debit pays a supplier, a credit collects from a customer.
     */
    private function payment(float $amount, ?Party $party = null, string $kind = 'supplier', string $direction = 'debit'): CompanyTransaction
    {
        $existing = CompanyTransaction::withTrashed()->pluck('id');

        app(CariEntryService::class)->create([
            'kind' => $kind,
            'direction' => $direction,
            'party_id' => ($party ?? $this->supplier)->id,
            'amount' => (string) $amount,
            'occurred_on' => '2026-10-05',
            'payment_method' => 'cash',
            'bank_id' => $this->cashBox->id,
        ], null);

        return CompanyTransaction::query()->whereNotIn('id', $existing)->sole();
    }

    /**
     * A debit on the supplier without a payment method: a booking, not money paid.
     */
    private function unpaidEntry(float $amount): CompanyTransaction
    {
        app(CariEntryService::class)->create([
            'kind' => 'supplier',
            'direction' => 'debit',
            'party_id' => $this->supplier->id,
            'amount' => (string) $amount,
            'occurred_on' => '2026-10-05',
        ], null);

        return CompanyTransaction::query()->whereNull('payment_method')->firstOrFail();
    }

    private function assertRejected(Closure $action, string $message): void
    {
        try {
            $action();
        } catch (OperationRejectedException $exception) {
            $this->assertStringContainsString($message, $exception->getMessage());

            return;
        }

        $this->fail('The operation was not rejected.');
    }

    private function payments(): InvoicePaymentService
    {
        return app(InvoicePaymentService::class);
    }

    private function invoices(): InvoiceService
    {
        return app(InvoiceService::class);
    }

    private function assertAuditPasses(): void
    {
        $this->artisan('ledger:audit')
            ->expectsOutputToContain('Cari, belgeler ve raporlar birebir tutuyor.')
            ->assertSuccessful();
    }
}
