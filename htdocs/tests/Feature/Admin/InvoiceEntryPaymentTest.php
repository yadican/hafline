<?php

namespace Tests\Feature\Admin;

use App\Enums\AccountType;
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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InvoiceEntryPaymentTest extends TestCase
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

    public function test_the_payment_status_is_offered_on_the_new_invoice_form_only(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.invoices.create'))
            ->assertOk()
            ->assertSee('name="payment_status"', false)
            ->assertSee('value="partial"', false)
            ->assertSee('name="payment[amount]"', false)
            ->assertSee('name="payment[payment_method]"', false)
            ->assertSee('name="payment[bank_id]"', false);

        $this->actingAs($this->admin)
            ->get(route('admin.invoices.edit', Invoice::factory()->create()))
            ->assertOk()
            ->assertDontSee('name="payment_status"', false)
            ->assertDontSee('name="payment[payment_method]"', false);
    }

    public function test_a_purchase_entered_as_paid_in_cash_settles_the_supplier_from_the_cash_box(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.invoices.store'), $this->payload('purchase', [
                'payment_status' => 'paid',
                'due_on' => '2026-11-30',
                'payment' => ['payment_method' => 'cash', 'bank_id' => $this->cashBox->id, 'amount' => '1'],
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.invoices.index'))
            ->assertSessionHas('success', 'Fatura kaydedildi.');

        $invoice = Invoice::query()->sole();
        $entry = CompanyTransaction::query()->sole();
        $this->assertNull($invoice->due_on);
        $this->assertSame(
            [PartyRole::Supplier, '1200.00', '0.00', PaymentMethod::Cash, $this->cashBox->id, '2026-10-01', 'A-1023', $this->admin->id],
            [$entry->role, $entry->debit, $entry->credit, $entry->payment_method, $entry->bank_id, $entry->occurred_on->toDateString(), $entry->document_no, $entry->created_by],
        );
        $this->assertTrue(InvoicePayment::query()->sole()->payment->is($entry));
        $this->assertSame([1200.0, 0.0, InvoicePaymentStatus::Paid], [$invoice->paidAmount(), $invoice->remainingAmount(), $invoice->paymentStatus()]);
        $this->assertSame(0.0, $this->partyBalance());
        $this->assertSame(-1200.0, $this->balance($this->cashBox));
        $this->assertAuditPasses();
    }

    public function test_a_sale_entered_as_collected_by_transfer_credits_the_customer_and_the_bank(): void
    {
        $bank = Bank::factory()->create(['type' => AccountType::Bank]);

        $this->actingAs($this->admin)
            ->post(route('admin.invoices.store'), $this->payload('sale', [
                'payment_status' => 'paid',
                'payment' => ['payment_method' => 'bank_transfer', 'bank_id' => $bank->id],
            ]))
            ->assertSessionHasNoErrors();

        $entry = CompanyTransaction::query()->sole();
        $this->assertSame([PartyRole::Customer, '0.00', '1200.00'], [$entry->role, $entry->debit, $entry->credit]);
        $this->assertSame(InvoicePaymentStatus::Paid, Invoice::query()->sole()->paymentStatus());
        $this->assertSame(0.0, $this->partyBalance());
        $this->assertSame(1200.0, $this->balance($bank));
        $this->assertAuditPasses();
    }

    public function test_a_purchase_paid_by_cheque_puts_it_into_the_portfolio_and_moves_no_account(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.invoices.store'), $this->payload('purchase', [
                'payment_status' => 'paid',
                'payment' => ['payment_method' => 'cheque', 'bank_id' => $this->cashBox->id, 'cheque_no' => 'Ç-55', 'due_on' => '2026-12-10'],
            ]))
            ->assertSessionHasNoErrors();

        $instrument = FinancialInstrument::query()->sole();
        $this->assertNull(CompanyTransaction::query()->sole()->bank_id);
        $this->assertSame([InstrumentStatus::Issued, '1200.00', 'Ç-55', '2026-12-10'], [$instrument->status, $instrument->amount, $instrument->number, $instrument->due_on->toDateString()]);
        $this->assertSame(InvoicePaymentStatus::Paid, Invoice::query()->sole()->paymentStatus());
        $this->assertSame(0.0, $this->balance($this->cashBox));
        $this->assertAuditPasses();
    }

    public function test_an_unpaid_invoice_keeps_its_due_date_and_writes_no_payment(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.invoices.store'), $this->payload('purchase', [
                'payment_status' => 'unpaid',
                'due_on' => '2026-11-30',
                'payment' => ['payment_method' => 'cash', 'bank_id' => $this->cashBox->id],
            ]))
            ->assertSessionHasNoErrors();

        $invoice = Invoice::query()->sole();
        $this->assertSame(['2026-11-30', InvoicePaymentStatus::Unpaid], [$invoice->due_on->toDateString(), $invoice->paymentStatus()]);
        $this->assertSame(-1200.0, $this->partyBalance());
        $this->assertSame(0, CompanyTransaction::query()->count());
    }

    public function test_an_invalid_payment_saves_neither_the_invoice_nor_the_payment(): void
    {
        $bank = Bank::factory()->create(['type' => AccountType::Bank]);

        $this->actingAs($this->admin)
            ->from(route('admin.invoices.create'))
            ->post(route('admin.invoices.store'), $this->payload('purchase', [
                'payment_status' => 'paid',
                'payment' => ['payment_method' => 'cash', 'bank_id' => $bank->id],
            ]))
            ->assertRedirect(route('admin.invoices.create'))
            ->assertSessionHasErrors(['payment.bank_id' => 'Bu ödeme tipi için kasa hesabı seçin.']);

        $this->actingAs($this->admin)
            ->post(route('admin.invoices.store'), $this->payload('purchase', [
                'payment_status' => 'paid',
                'payment' => ['payment_method' => 'cheque'],
            ]))
            ->assertSessionHasErrors(['payment.due_on']);

        $this->actingAs($this->admin)
            ->post(route('admin.invoices.store'), $this->payload('purchase', ['payment_status' => 'paid']))
            ->assertSessionHasErrors(['payment.payment_method']);

        $this->assertNothingWritten();
    }

    public function test_a_purchase_entered_as_partly_paid_pays_the_amount_and_keeps_the_due_date_for_the_rest(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.invoices.store'), $this->payload('purchase', [
                'payment_status' => 'partial',
                'due_on' => '2026-11-30',
                'payment' => ['amount' => '450,50', 'payment_method' => 'cash', 'bank_id' => $this->cashBox->id],
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.invoices.index'));

        $invoice = Invoice::query()->sole();
        $entry = CompanyTransaction::query()->sole();
        $this->assertSame('2026-11-30', $invoice->due_on->toDateString());
        $this->assertSame(
            [PartyRole::Supplier, '450.50', '0.00', $this->cashBox->id, '2026-10-01'],
            [$entry->role, $entry->debit, $entry->credit, $entry->bank_id, $entry->occurred_on->toDateString()],
        );
        $this->assertSame('450.50', InvoicePayment::query()->sole()->amount);
        $this->assertSame([450.5, 749.5, InvoicePaymentStatus::Partial], [$invoice->paidAmount(), $invoice->remainingAmount(), $invoice->paymentStatus()]);
        $this->assertSame(-749.5, $this->partyBalance());
        $this->assertSame(-450.5, $this->balance($this->cashBox));
        $this->assertAuditPasses();
    }

    public function test_a_partly_collected_sale_whose_amount_is_the_total_counts_as_collected(): void
    {
        $bank = Bank::factory()->create(['type' => AccountType::Bank]);

        $this->actingAs($this->admin)
            ->post(route('admin.invoices.store'), $this->payload('sale', [
                'payment_status' => 'partial',
                'payment' => ['amount' => '1200', 'payment_method' => 'bank_transfer', 'bank_id' => $bank->id],
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame(InvoicePaymentStatus::Paid, Invoice::query()->sole()->paymentStatus());
        $this->assertSame(0.0, $this->partyBalance());
        $this->assertSame(1200.0, $this->balance($bank));
        $this->assertAuditPasses();
    }

    public function test_a_partial_amount_must_be_above_zero_and_within_the_total(): void
    {
        $partial = fn (?string $amount): array => $this->payload('purchase', [
            'payment_status' => 'partial',
            'payment' => ['amount' => $amount, 'payment_method' => 'cash', 'bank_id' => $this->cashBox->id],
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.invoices.store'), $partial('1200.01'))
            ->assertSessionHasErrors(['payment.amount' => 'Tutar faturanın kalanını (1.200,00 TL) aşamaz.']);

        foreach (['0', '', null] as $amount) {
            $this->actingAs($this->admin)
                ->post(route('admin.invoices.store'), $partial($amount))
                ->assertSessionHasErrors(['payment.amount']);
        }

        $this->assertNothingWritten();
    }

    public function test_paying_at_entry_needs_the_cari_entry_create_permission(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(['view-dashboard', 'view-invoice', 'create-invoice']);
        $paid = $this->payload('purchase', [
            'payment_status' => 'paid',
            'payment' => ['payment_method' => 'cash', 'bank_id' => $this->cashBox->id],
        ]);

        $this->actingAs($user)
            ->get(route('admin.invoices.create'))
            ->assertOk()
            ->assertDontSee('name="payment_status"', false);

        $this->actingAs($user)
            ->post(route('admin.invoices.store'), $paid)
            ->assertForbidden();

        $this->assertNothingWritten();

        $this->actingAs($user)
            ->post(route('admin.invoices.store'), $this->payload('purchase'))
            ->assertSessionHasNoErrors();

        $this->assertSame(InvoicePaymentStatus::Unpaid, Invoice::query()->sole()->paymentStatus());
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(string $direction, array $overrides = []): array
    {
        return [
            'direction' => $direction,
            'party_id' => $this->party->id,
            'invoice_no' => 'A-1023',
            'issued_on' => '2026-10-01',
            'lines' => [['description' => 'Malzeme', 'quantity' => '2', 'unit_price' => '500', 'vat_rate' => '20']],
            ...$overrides,
        ];
    }

    private function partyBalance(): float
    {
        return (float) DB::table('ledger_entries')->where('party_id', $this->party->id)->sum(DB::raw('debit - credit'));
    }

    private function balance(Bank $account): float
    {
        return app(BankRepository::class)->balances([$account->id])[$account->id];
    }

    private function assertNothingWritten(): void
    {
        $this->assertSame(0, Invoice::withTrashed()->count());
        $this->assertSame(0, CompanyTransaction::withTrashed()->count());
        $this->assertSame(0, InvoicePayment::withTrashed()->count());
        $this->assertSame(0, FinancialInstrument::withTrashed()->count());
        $this->assertSame(0, DB::table('ledger_entries')->count());
    }

    private function assertAuditPasses(): void
    {
        $this->artisan('ledger:audit')
            ->expectsOutputToContain('Cari, belgeler ve raporlar birebir tutuyor.')
            ->assertSuccessful();
    }
}
