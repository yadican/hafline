<?php

namespace Tests\Feature\Admin;

use App\Enums\AccountType;
use App\Enums\InvoicePaymentStatus;
use App\Enums\PartyRole;
use App\Models\Bank;
use App\Models\CompanyTransaction;
use App\Models\FinancialInstrument;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Party;
use App\Models\User;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class InvoiceUnmatchedPaymentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Party $party;

    private Bank $cashBox;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->yonetici()->create();
        $this->party = Party::factory()->company()->withRole(PartyRole::Supplier)->create();
        $this->cashBox = Bank::factory()->create(['type' => AccountType::Cash]);
    }

    public function test_an_advance_paid_before_the_invoice_is_listed_and_matched_later(): void
    {
        $advance = $this->payment(400, '2026-09-20', ['document_no' => 'AV-1']);
        $invoice = $this->invoice('A-1', 1000);

        $this->actingAs($this->admin)
            ->get(route('admin.invoices.show', $invoice))
            ->assertOk()
            ->assertSee('AV-1')
            ->assertSee('value="'.$advance->id.'"', false)
            ->assertSee('value="400.00"', false)
            ->assertSee(route('admin.invoices.matches.distribute', $invoice), false);

        $this->match($invoice, $advance)
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.invoices.show', $invoice))
            ->assertSessionHas('success', 'Ödeme faturaya bağlandı.');

        $this->assertSame([400.0, 600.0, InvoicePaymentStatus::Partial], $this->state($invoice));
        $this->assertTrue(InvoicePayment::query()->sole()->payment->is($advance));
        $this->assertSame(1, CompanyTransaction::query()->count());
        $this->assertAuditPasses();

        $this->actingAs($this->admin)
            ->get(route('admin.invoices.show', $invoice))
            ->assertDontSee('name="payment_id"', false);
    }

    public function test_an_overpayment_waits_with_its_unmatched_part_for_another_invoice(): void
    {
        $first = $this->invoice('A-1', 1000);
        $second = $this->invoice('A-2', 800);
        $payment = $this->payment(1500, '2026-10-05');

        $this->match($first, $payment)->assertSessionHasNoErrors();
        $this->assertSame([1000.0, 0.0, InvoicePaymentStatus::Paid], $this->state($first));

        $this->actingAs($this->admin)
            ->get(route('admin.invoices.show', $second))
            ->assertSee('value="500.00"', false);

        $this->match($second, $payment, '500.01')
            ->assertSessionHas('error', 'Bağlanan tutar, ödeme tutarının bağlanmamış kısmını (500,00 TL) aşamaz.');
        $this->match($second, $payment, '300')->assertSessionHasNoErrors();

        $this->assertSame([300.0, 500.0, InvoicePaymentStatus::Partial], $this->state($second));

        $this->actingAs($this->admin)
            ->get(route('admin.invoices.show', $second))
            ->assertSee('value="200.00"', false);

        $this->match($second, $payment)->assertSessionHasNoErrors();

        $this->assertSame([500.0, 300.0, InvoicePaymentStatus::Partial], $this->state($second));
        $this->assertSame('500.00', $second->payments()->sole()->amount);
        $this->assertAuditPasses();
    }

    public function test_distributing_settles_the_remaining_from_the_oldest_payment(): void
    {
        $newest = $this->payment(500, '2026-09-15');
        $oldest = $this->payment(300, '2026-09-01');
        $middle = $this->payment(400, '2026-09-10');
        $invoice = $this->invoice('A-1', 1000);

        $this->actingAs($this->admin)
            ->post(route('admin.invoices.matches.distribute', $invoice))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Boşta kalan ödemeler faturaya dağıtıldı.');

        $this->assertSame([1000.0, 0.0, InvoicePaymentStatus::Paid], $this->state($invoice));
        $this->assertSame(
            [$oldest->id => '300.00', $middle->id => '400.00', $newest->id => '300.00'],
            $invoice->payments()->pluck('amount', 'payment_id')->all(),
        );

        $other = $this->invoice('A-2', 1000);

        $this->actingAs($this->admin)->post(route('admin.invoices.matches.distribute', $other))->assertSessionHasNoErrors();

        $this->assertSame([200.0, 800.0, InvoicePaymentStatus::Partial], $this->state($other));
        $this->assertSame([$newest->id => '200.00'], $other->payments()->pluck('amount', 'payment_id')->all());
        $this->assertAuditPasses();

        $this->actingAs($this->admin)
            ->post(route('admin.invoices.matches.distribute', $other))
            ->assertSessionHas('error', 'Faturaya bağlanacak boşta ödeme yok.');
    }

    public function test_another_partys_payment_is_neither_listed_nor_matched(): void
    {
        $otherParty = Party::factory()->company()->withRole(PartyRole::Supplier)->create();
        $foreign = $this->payment(400, '2026-09-20', ['party_id' => $otherParty->id]);
        $invoice = $this->invoice('A-1', 1000);

        $this->actingAs($this->admin)
            ->get(route('admin.invoices.show', $invoice))
            ->assertOk()
            ->assertDontSee('value="'.$foreign->id.'"', false)
            ->assertDontSee(route('admin.invoices.matches.distribute', $invoice), false);

        $this->match($invoice, $foreign)
            ->assertSessionHas('error', 'Bu kayıt, faturanın carisine yapılmış bir ödeme değil.');
        $this->actingAs($this->admin)
            ->post(route('admin.invoices.matches.distribute', $invoice))
            ->assertSessionHas('error', 'Faturaya bağlanacak boşta ödeme yok.');

        $this->assertSame(0, InvoicePayment::withTrashed()->count());
    }

    public function test_a_payment_whose_cheque_returned_unpaid_is_not_listed(): void
    {
        $this->payment(400, '2026-09-20', ['payment_method' => 'cheque', 'bank_id' => null, 'cheque_no' => 'Ç-56', 'due_on' => '2026-12-10']);
        $invoice = $this->invoice('A-1', 1000);
        $cheque = FinancialInstrument::query()->sole();

        $this->actingAs($this->admin)
            ->get(route('admin.invoices.show', $invoice))
            ->assertSee(route('admin.invoices.matches.distribute', $invoice), false);

        $this->actingAs($this->admin)
            ->post(route('admin.financial-instruments.movements.store', $cheque), ['type' => 'bounce', 'occurred_on' => '2026-10-20'])
            ->assertSessionHas('success');

        $this->actingAs($this->admin)
            ->get(route('admin.invoices.show', $invoice))
            ->assertOk()
            ->assertDontSee(route('admin.invoices.matches.distribute', $invoice), false);
    }

    public function test_removing_a_match_opens_the_invoice_and_frees_the_payment(): void
    {
        $invoice = $this->invoice('A-1', 1000);
        $payment = $this->payment(1000, '2026-10-05');
        $this->match($invoice, $payment)->assertSessionHasNoErrors();
        $match = InvoicePayment::query()->sole();
        $other = $this->invoice('A-2', 500);

        $this->actingAs($this->admin)
            ->delete(route('admin.invoices.matches.destroy', [$other, $match]))
            ->assertNotFound();

        $this->actingAs($this->admin)
            ->get(route('admin.invoices.show', $invoice))
            ->assertSee(route('admin.invoices.matches.destroy', [$invoice, $match]), false);

        $this->actingAs($this->admin)
            ->delete(route('admin.invoices.matches.destroy', [$invoice, $match]))
            ->assertRedirect(route('admin.invoices.show', $invoice))
            ->assertSessionHas('success', 'Bağlantı kaldırıldı.');

        $this->assertSame([0.0, 1000.0, InvoicePaymentStatus::Unpaid], $this->state($invoice));
        $this->assertSoftDeleted($match);
        $this->assertNotNull($payment->fresh());
        $this->assertAuditPasses();

        $this->actingAs($this->admin)
            ->get(route('admin.invoices.show', $other))
            ->assertSee('value="'.$payment->id.'"', false)
            ->assertSee('value="500.00"', false);
    }

    public function test_matching_needs_invoice_edit_permission_only(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(['view-dashboard', 'view-invoice']);
        $invoice = $this->invoice('A-1', 1000);
        $payment = $this->payment(400, '2026-09-20');

        $this->actingAs($user)
            ->get(route('admin.invoices.show', $invoice))
            ->assertOk()
            ->assertDontSee(route('admin.invoices.matches.store', $invoice), false)
            ->assertDontSee(route('admin.invoices.matches.distribute', $invoice), false);

        $this->match($invoice, $payment, null, $user)->assertForbidden();
        $this->actingAs($user)->post(route('admin.invoices.matches.distribute', $invoice))->assertForbidden();
        $this->assertSame(0, InvoicePayment::withTrashed()->count());

        $user->givePermissionTo('edit-invoice');

        $this->match($invoice, $payment, null, $user->fresh())->assertSessionHasNoErrors();
        $match = InvoicePayment::query()->sole();

        $user->revokePermissionTo('edit-invoice');

        $this->actingAs($user->fresh())
            ->delete(route('admin.invoices.matches.destroy', [$invoice, $match]))
            ->assertForbidden();
        $this->assertNotSoftDeleted($match);
    }

    private function invoice(string $number, float $total): Invoice
    {
        return app(InvoiceService::class)->create([
            'direction' => 'purchase',
            'party_id' => $this->party->id,
            'invoice_no' => $number,
            'issued_on' => '2026-10-01',
            'lines' => [['description' => 'Malzeme', 'quantity' => '1', 'unit_price' => (string) $total, 'vat_rate' => '0']],
        ], $this->admin->id);
    }

    /**
     * A payment to the supplier written on Cari hareketleri, not matched to any invoice.
     *
     * @param  array<string, ?string>  $overrides
     */
    private function payment(float $amount, string $date, array $overrides = []): CompanyTransaction
    {
        $before = CompanyTransaction::query()->pluck('id');

        $this->actingAs($this->admin)->post(route('admin.cari-entries.store'), [
            'kind' => 'supplier',
            'direction' => 'debit',
            'party_id' => $this->party->id,
            'occurred_on' => $date,
            'amount' => (string) $amount,
            'payment_method' => 'cash',
            'bank_id' => $this->cashBox->id,
            ...$overrides,
        ])->assertSessionHasNoErrors();

        return CompanyTransaction::query()->whereNotIn('id', $before)->sole();
    }

    private function match(Invoice $invoice, CompanyTransaction $payment, ?string $amount = null, ?User $user = null): TestResponse
    {
        return $this->actingAs($user ?? $this->admin)->post(route('admin.invoices.matches.store', $invoice), [
            'payment_type' => $payment->getMorphClass(),
            'payment_id' => $payment->id,
            'amount' => $amount,
        ]);
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
