<?php

namespace Tests\Feature\Admin;

use App\Enums\AccountType;
use App\Enums\InvoicePaymentStatus;
use App\Enums\PartyRole;
use App\Models\Bank;
use App\Models\CompanyTransaction;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Party;
use App\Models\User;
use App\Services\InvoicePaymentService;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoicePaymentReleaseTest extends TestCase
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

    public function test_deleting_the_payment_reopens_the_invoice(): void
    {
        $invoice = $this->invoice('A-1', 1000);
        $this->pay($invoice, 400);
        $entry = CompanyTransaction::query()->sole();

        $this->actingAs($this->admin)
            ->delete(route('admin.cari-entries.destroy', ['kind' => 'supplier', 'entry' => $entry->id]))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Cari hareketi silindi.');

        $this->assertSoftDeleted($entry);
        $this->assertSoftDeleted(InvoicePayment::withTrashed()->sole());
        $this->assertSame([0.0, 1000.0, InvoicePaymentStatus::Unpaid], $this->state($invoice));
        $this->assertAuditPasses();
    }

    public function test_lowering_the_amount_cuts_the_match_to_it(): void
    {
        $invoice = $this->invoice('A-1', 1000);
        $this->pay($invoice, 1000);
        $update = route('admin.cari-entries.update', ['kind' => 'supplier', 'entry' => CompanyTransaction::query()->sole()->id]);

        $this->actingAs($this->admin)->put($update, $this->payload(['amount' => '600']))->assertSessionHasNoErrors();

        $this->assertSame([600.0, 400.0, InvoicePaymentStatus::Partial], $this->state($invoice));
        $this->assertSame('600.00', InvoicePayment::query()->sole()->amount);

        $this->actingAs($this->admin)->put($update, $this->payload(['amount' => '600', 'description' => 'Not']))->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->put($update, $this->payload(['amount' => '900']))->assertSessionHasNoErrors();

        $this->assertSame([600.0, 400.0, InvoicePaymentStatus::Partial], $this->state($invoice));
        $this->assertAuditPasses();
    }

    public function test_lowering_a_payment_split_over_invoices_cuts_the_newest_match_first(): void
    {
        $first = $this->invoice('A-1', 600);
        $second = $this->invoice('A-2', 400);
        $this->actingAs($this->admin)->post(route('admin.cari-entries.store'), $this->payload(['amount' => '1000']))->assertSessionHasNoErrors();
        $entry = CompanyTransaction::query()->sole();
        $matches = app(InvoicePaymentService::class);
        $this->travelTo('2026-10-30 09:00:00');
        $matches->match($first, $entry);
        $this->travelTo('2026-10-30 10:00:00');
        $matches->match($second, $entry);
        $update = route('admin.cari-entries.update', ['kind' => 'supplier', 'entry' => $entry->id]);

        $this->actingAs($this->admin)->put($update, $this->payload(['amount' => '700']))->assertSessionHasNoErrors();

        $this->assertSame([600.0, 0.0, InvoicePaymentStatus::Paid], $this->state($first));
        $this->assertSame([100.0, 300.0, InvoicePaymentStatus::Partial], $this->state($second));

        $this->actingAs($this->admin)->put($update, $this->payload(['amount' => '500']))->assertSessionHasNoErrors();

        $this->assertSame([500.0, 100.0, InvoicePaymentStatus::Partial], $this->state($first));
        $this->assertSame([0.0, 400.0, InvoicePaymentStatus::Unpaid], $this->state($second));
        $this->assertSame(1, InvoicePayment::onlyTrashed()->where('invoice_id', $second->id)->count());
        $this->assertAuditPasses();
    }

    public function test_moving_the_payment_to_another_party_drops_its_match(): void
    {
        $invoice = $this->invoice('A-1', 1000);
        $this->pay($invoice, 1000);
        $other = Party::factory()->company()->withRole(PartyRole::Supplier)->create();

        $this->actingAs($this->admin)
            ->put(route('admin.cari-entries.update', ['kind' => 'supplier', 'entry' => CompanyTransaction::query()->sole()->id]), $this->payload(['amount' => '1000', 'party_id' => $other->id]))
            ->assertSessionHasNoErrors();

        $this->assertSame([0.0, 1000.0, InvoicePaymentStatus::Unpaid], $this->state($invoice));
        $this->assertSame(0, InvoicePayment::query()->count());
        $this->assertAuditPasses();
    }

    public function test_an_invoice_with_payments_cannot_be_deleted_until_they_are(): void
    {
        $invoice = $this->invoice('A-1', 1000);
        $this->pay($invoice, 400);

        $this->actingAs($this->admin)
            ->get(route('admin.invoices.show', $invoice))
            ->assertOk()
            ->assertDontSee('Fatura silinsin mi?')
            ->assertSee('Ödeme bağlı fatura silinemez.');

        $this->actingAs($this->admin)
            ->from(route('admin.invoices.show', $invoice))
            ->delete(route('admin.invoices.destroy', $invoice))
            ->assertRedirect(route('admin.invoices.show', $invoice))
            ->assertSessionHas('error', 'Ödeme bağlanmış fatura silinemez. Önce faturaya bağlı ödeme kayıtlarını Cari hareketleri ekranından silin.');

        $this->assertNotSoftDeleted($invoice);
        $this->assertSame([400.0, 600.0, InvoicePaymentStatus::Partial], $this->state($invoice));

        $this->actingAs($this->admin)
            ->delete(route('admin.cari-entries.destroy', ['kind' => 'supplier', 'entry' => CompanyTransaction::query()->sole()->id]))
            ->assertSessionHasNoErrors();
        $this->actingAs($this->admin)
            ->delete(route('admin.invoices.destroy', $invoice))
            ->assertRedirect(route('admin.invoices.index'));

        $this->assertSoftDeleted($invoice);
        $this->assertAuditPasses();
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

    private function pay(Invoice $invoice, float $amount): void
    {
        app(InvoiceService::class)->pay($invoice, [
            'amount' => (string) $amount,
            'occurred_on' => '2026-10-10',
            'payment_method' => 'cash',
            'bank_id' => $this->cashBox->id,
        ], $this->admin->id);
    }

    /**
     * @param  array<string, string>  $overrides
     * @return array<string, string>
     */
    private function payload(array $overrides): array
    {
        return [
            'kind' => 'supplier',
            'direction' => 'debit',
            'party_id' => $this->party->id,
            'occurred_on' => '2026-10-10',
            'payment_method' => 'cash',
            'bank_id' => $this->cashBox->id,
            ...$overrides,
        ];
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
