<?php

namespace Tests\Feature\Admin;

use App\Models\Invoice;
use App\Models\Party;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\Ledger\Ledger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceDetailTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->yonetici()->create();
    }

    public function test_detail_shows_the_invoice_its_lines_and_its_party_row(): void
    {
        $supplier = Party::factory()->company()->create(['name' => 'Yıldız Yapı']);
        $invoice = $this->invoice($supplier, [
            ['description' => 'Çimento', 'quantity' => '10', 'unit_price' => '250', 'vat_rate' => '20'],
            ['description' => 'Nakliye', 'quantity' => '1', 'unit_price' => '500', 'vat_rate' => '0'],
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.invoices.show', $invoice))
            ->assertOk()
            ->assertSee('A-1023')
            ->assertSeeInOrder(['Matrah', '3.000,00 TL', 'KDV', '500,00 TL', 'Toplam', '3.500,00 TL', 'Ödenmedi'])
            ->assertSeeInOrder(['Çimento', '10', '250,00', '2.500,00', '%20', '500,00', '3.000,00'])
            ->assertSee('Nakliye')
            ->assertSeeInOrder(['Cari satırı', 'Alacak', '3.500,00 TL'])
            ->assertSee(route('admin.parties.show', $supplier), false)
            ->assertSee(route('admin.invoices.destroy', $invoice), false);
    }

    public function test_detail_of_an_invoice_without_vat_hides_the_vat_columns(): void
    {
        $invoice = $this->invoice(Party::factory()->company()->create(), [
            ['description' => 'Kum', 'quantity' => '4', 'unit_price' => '150', 'vat_rate' => '0'],
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.invoices.show', $invoice))
            ->assertOk()
            ->assertSee('600,00 TL')
            ->assertDontSee('Matrah')
            ->assertDontSee('KDV %');
    }

    public function test_list_links_each_invoice_to_its_detail(): void
    {
        $invoice = Invoice::factory()->create();

        $this->actingAs($this->admin)
            ->get(route('admin.invoices.index'))
            ->assertOk()
            ->assertSee(route('admin.invoices.show', $invoice), false)
            ->assertSee(route('admin.invoices.destroy', $invoice), false);
    }

    public function test_party_statement_links_the_invoice_row_to_the_invoice(): void
    {
        $supplier = Party::factory()->company()->create();
        $invoice = $this->invoice($supplier, [
            ['description' => 'Kum', 'quantity' => '4', 'unit_price' => '150', 'vat_rate' => '0'],
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.parties.show', $supplier))
            ->assertOk()
            ->assertSee('<a href="'.route('admin.invoices.show', $invoice).'">Alış faturası A-1023</a>', false);
    }

    public function test_deleting_reverses_the_party_row_and_removes_the_invoice(): void
    {
        $invoice = $this->invoice(Party::factory()->company()->create(), [
            ['description' => 'Kum', 'quantity' => '4', 'unit_price' => '150', 'vat_rate' => '0'],
        ]);

        $this->actingAs($this->admin)
            ->delete(route('admin.invoices.destroy', $invoice))
            ->assertRedirect(route('admin.invoices.index'))
            ->assertSessionHas('success', 'Fatura silindi.');

        $this->assertSoftDeleted($invoice);
        $this->assertCount(0, app(Ledger::class)->live($invoice));
        $this->assertSame(0.0, round($invoice->ledgerEntries()->get()->sum(fn ($entry): float => (float) $entry->credit - (float) $entry->debit), 2));
        $this->artisan('ledger:audit')->expectsOutputToContain('Cari, belgeler ve raporlar birebir tutuyor.');
    }

    public function test_viewer_sees_the_detail_but_cannot_delete(): void
    {
        $viewer = User::factory()->create();
        $viewer->givePermissionTo(['view-dashboard', 'view-invoice']);
        $invoice = $this->invoice(Party::factory()->company()->create(), [
            ['description' => 'Kum', 'quantity' => '1', 'unit_price' => '100', 'vat_rate' => '0'],
        ]);

        $this->actingAs($viewer)
            ->get(route('admin.invoices.show', $invoice))
            ->assertOk()
            ->assertDontSee(route('admin.invoices.destroy', $invoice), false)
            ->assertDontSee(route('admin.invoices.edit', $invoice), false);

        $this->actingAs($viewer)
            ->delete(route('admin.invoices.destroy', $invoice))
            ->assertForbidden();

        $this->assertNotSoftDeleted($invoice);
        $this->assertCount(1, app(Ledger::class)->live($invoice));
    }

    public function test_detail_requires_the_view_permission(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('view-dashboard');

        $this->actingAs($user)
            ->get(route('admin.invoices.show', Invoice::factory()->create()))
            ->assertForbidden();
    }

    /**
     * @param  list<array<string, string>>  $lines
     */
    private function invoice(Party $party, array $lines): Invoice
    {
        return app(InvoiceService::class)->create([
            'direction' => 'purchase',
            'party_id' => $party->id,
            'invoice_no' => 'A-1023',
            'issued_on' => today()->toDateString(),
            'lines' => $lines,
        ], $this->admin->id);
    }
}
