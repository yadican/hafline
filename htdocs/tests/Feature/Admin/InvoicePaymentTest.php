<?php

namespace Tests\Feature\Admin;

use App\Enums\AccountType;
use App\Enums\InstrumentDirection;
use App\Enums\InstrumentStatus;
use App\Enums\InvoicePaymentStatus;
use App\Enums\PartyRole;
use App\Enums\PaymentMethod;
use App\Models\Bank;
use App\Models\CompanyTransaction;
use App\Models\FinancialInstrument;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Party;
use App\Models\User;
use App\Repositories\BankRepository;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoicePaymentTest extends TestCase
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

    public function test_partial_cash_payment_debits_the_supplier_and_leaves_the_rest_open(): void
    {
        $invoice = $this->invoice('purchase', 1000);

        $this->actingAs($this->admin)
            ->post(route('admin.invoices.payments.store', $invoice), [
                'amount' => '400',
                'occurred_on' => '2026-10-10',
                'payment_method' => 'cash',
                'bank_id' => $this->cashBox->id,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.invoices.show', $invoice))
            ->assertSessionHas('success', 'Ödeme kaydedildi.');

        $entry = CompanyTransaction::query()->sole();
        $this->assertSame(
            [PartyRole::Supplier, $this->party->id, '400.00', '0.00', PaymentMethod::Cash, $this->cashBox->id, 'A-1023', 'Alış faturası A-1023', $this->admin->id],
            [$entry->role, $entry->party_id, $entry->debit, $entry->credit, $entry->payment_method, $entry->bank_id, $entry->document_no, $entry->description, $entry->created_by],
        );
        $this->assertTrue(InvoicePayment::query()->sole()->payment->is($entry));
        $this->assertSame([400.0, 600.0, InvoicePaymentStatus::Partial], $this->state($invoice));
        $this->assertSame(-400.0, $this->balance($this->cashBox));
        $this->assertAuditPasses();

        $this->actingAs($this->admin)
            ->get(route('admin.invoices.show', $invoice))
            ->assertOk()
            ->assertSee(route('admin.invoices.payments.store', $invoice), false)
            ->assertSee(route('admin.cari-entries.show', ['kind' => 'supplier', 'entry' => $entry]), false)
            ->assertSee('value="600.00"', false);
    }

    public function test_full_collection_of_a_sale_credits_the_customer_and_closes_the_invoice(): void
    {
        $bank = Bank::factory()->create(['type' => AccountType::Bank]);
        $invoice = $this->invoice('sale', 1500);

        $this->actingAs($this->admin)
            ->post(route('admin.invoices.payments.store', $invoice), [
                'amount' => '1500,00',
                'occurred_on' => '2026-10-12',
                'payment_method' => 'bank_transfer',
                'bank_id' => $bank->id,
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Tahsilat kaydedildi.');

        $entry = CompanyTransaction::query()->sole();
        $this->assertSame([PartyRole::Customer, '0.00', '1500.00'], [$entry->role, $entry->debit, $entry->credit]);
        $this->assertSame([1500.0, 0.0, InvoicePaymentStatus::Paid], $this->state($invoice));
        $this->assertSame(1500.0, $this->balance($bank));
        $this->assertAuditPasses();

        $this->actingAs($this->admin)
            ->get(route('admin.invoices.show', $invoice))
            ->assertOk()
            ->assertSee('Tahsil edildi')
            ->assertDontSee(route('admin.invoices.payments.store', $invoice), false);
    }

    public function test_paying_with_a_cheque_puts_it_into_the_portfolio_and_moves_no_account(): void
    {
        $invoice = $this->invoice('purchase', 1000);

        $this->actingAs($this->admin)
            ->post(route('admin.invoices.payments.store', $invoice), [
                'amount' => '1000',
                'occurred_on' => '2026-10-10',
                'payment_method' => 'cheque',
                'bank_id' => $this->cashBox->id,
                'cheque_no' => 'Ç-55',
                'due_on' => '2026-12-10',
            ])
            ->assertSessionHasNoErrors();

        $entry = CompanyTransaction::query()->sole();
        $instrument = FinancialInstrument::query()->sole();
        $this->assertNull($entry->bank_id);
        $this->assertTrue($instrument->source->is($entry));
        $this->assertSame(
            [PaymentMethod::Cheque, InstrumentDirection::Given, InstrumentStatus::Issued, '1000.00', 'Ç-55', '2026-12-10', $this->party->id],
            [$instrument->payment_method, $instrument->direction, $instrument->status, $instrument->amount, $instrument->number, $instrument->due_on->toDateString(), $instrument->party_id],
        );
        $this->assertSame(InvoicePaymentStatus::Paid, $invoice->fresh()->paymentStatus());
        $this->assertSame(0.0, $this->balance($this->cashBox));
        $this->assertAuditPasses();
    }

    public function test_a_cheque_needs_its_due_date(): void
    {
        $invoice = $this->invoice('purchase', 1000);

        $this->actingAs($this->admin)
            ->post(route('admin.invoices.payments.store', $invoice), [
                'amount' => '1000',
                'occurred_on' => '2026-10-10',
                'payment_method' => 'cheque',
            ])
            ->assertSessionHasErrors('due_on');

        $this->assertNothingWritten();
    }

    public function test_the_account_must_suit_the_payment_method(): void
    {
        $bank = Bank::factory()->create(['type' => AccountType::Bank]);
        $invoice = $this->invoice('purchase', 1000);
        $payment = ['amount' => '400', 'occurred_on' => '2026-10-10'];

        $this->actingAs($this->admin)
            ->post(route('admin.invoices.payments.store', $invoice), [...$payment, 'payment_method' => 'cash', 'bank_id' => $bank->id])
            ->assertSessionHasErrors(['bank_id' => 'Bu ödeme tipi için kasa hesabı seçin.']);

        $this->actingAs($this->admin)
            ->post(route('admin.invoices.payments.store', $invoice), [...$payment, 'payment_method' => 'bank_transfer'])
            ->assertSessionHasErrors(['bank_id' => 'Paranın geçtiği hesabı seçin.']);

        $this->assertNothingWritten();
    }

    public function test_the_amount_cannot_exceed_the_invoice_remaining(): void
    {
        $invoice = $this->invoice('purchase', 1000);
        app(InvoiceService::class)->pay($invoice, ['amount' => '700', 'occurred_on' => '2026-10-10', 'payment_method' => 'cash', 'bank_id' => $this->cashBox->id], null);

        $this->actingAs($this->admin)
            ->post(route('admin.invoices.payments.store', $invoice), [
                'amount' => '300.01',
                'occurred_on' => '2026-10-11',
                'payment_method' => 'cash',
                'bank_id' => $this->cashBox->id,
            ])
            ->assertSessionHasErrors(['amount' => 'Tutar faturanın kalanını (300,00 TL) aşamaz.']);

        $this->assertSame(1, CompanyTransaction::query()->count());
        $this->assertSame(-700.0, $this->balance($this->cashBox));
    }

    public function test_paying_needs_both_invoice_edit_and_cari_entry_create_permissions(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(['view-dashboard', 'view-invoice', 'edit-invoice']);
        $invoice = $this->invoice('purchase', 1000);
        $payment = ['amount' => '400', 'occurred_on' => '2026-10-10', 'payment_method' => 'cash', 'bank_id' => $this->cashBox->id];

        $this->actingAs($user)
            ->get(route('admin.invoices.show', $invoice))
            ->assertOk()
            ->assertDontSee(route('admin.invoices.payments.store', $invoice), false);

        $this->actingAs($user)
            ->post(route('admin.invoices.payments.store', $invoice), $payment)
            ->assertForbidden();

        $this->assertNothingWritten();

        $user->givePermissionTo('create-cari-entry');

        $this->actingAs($user->fresh())
            ->post(route('admin.invoices.payments.store', $invoice), $payment)
            ->assertSessionHasNoErrors();

        $this->assertSame(InvoicePaymentStatus::Partial, $invoice->fresh()->paymentStatus());
    }

    private function invoice(string $direction, float $total): Invoice
    {
        return app(InvoiceService::class)->create([
            'direction' => $direction,
            'party_id' => $this->party->id,
            'invoice_no' => 'A-1023',
            'issued_on' => '2026-10-01',
            'lines' => [['description' => 'Malzeme', 'quantity' => '1', 'unit_price' => (string) $total, 'vat_rate' => '0']],
        ], $this->admin->id);
    }

    /**
     * @return array{0: float, 1: float, 2: InvoicePaymentStatus}
     */
    private function state(Invoice $invoice): array
    {
        $invoice = $invoice->fresh();

        return [$invoice->paidAmount(), $invoice->remainingAmount(), $invoice->paymentStatus()];
    }

    private function balance(Bank $account): float
    {
        return app(BankRepository::class)->balances([$account->id])[$account->id];
    }

    private function assertNothingWritten(): void
    {
        $this->assertSame(0, CompanyTransaction::withTrashed()->count());
        $this->assertSame(0, InvoicePayment::withTrashed()->count());
        $this->assertSame(0, FinancialInstrument::withTrashed()->count());
    }

    private function assertAuditPasses(): void
    {
        $this->artisan('ledger:audit')
            ->expectsOutputToContain('Cari, belgeler ve raporlar birebir tutuyor.')
            ->assertSuccessful();
    }
}
