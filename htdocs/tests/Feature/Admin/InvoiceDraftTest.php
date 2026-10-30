<?php

namespace Tests\Feature\Admin;

use App\Cache\DashboardCache;
use App\Enums\AccountType;
use App\Enums\InvoiceDirection;
use App\Enums\PartyRole;
use App\Models\Bank;
use App\Models\Invoice;
use App\Models\Party;
use App\Models\User;
use App\Services\Ledger\Ledger;
use App\Services\Reports\InvoiceAgingReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class InvoiceDraftTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->yonetici()->create();
    }

    public function test_draft_is_saved_without_ledger_and_shows_as_taslak(): void
    {
        $supplier = Party::factory()->company()->create();

        $this->actingAs($this->admin)
            ->post(route('admin.invoices.store'), [...$this->payload($supplier), 'is_draft' => '1'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.invoices.index'))
            ->assertSessionHas('success', 'Taslak fatura kaydedildi.');

        $invoice = Invoice::query()->sole();
        $this->assertTrue($invoice->isDraft());
        $this->assertCount(0, app(Ledger::class)->live($invoice));
        $this->assertAuditPasses();

        $this->actingAs($this->admin)
            ->get(route('admin.invoices.index'))
            ->assertOk()
            ->assertSee('Taslak', false)
            ->assertDontSee('Ödenmedi', false);

        $this->actingAs($this->admin)
            ->get(route('admin.invoices.show', $invoice))
            ->assertOk()
            ->assertSee('Taslak', false)
            ->assertSee('Onayla', false)
            ->assertSee('Taslak — henüz yazılmadı', false)
            ->assertDontSee('invoice-payment-open', false)
            ->assertDontSee('>Ödemeler<', false);
    }

    public function test_approve_posts_to_cari_and_clears_reminder_cache(): void
    {
        $this->travelTo('2026-10-30 12:00:00');
        $customer = Party::factory()->company()->withRole(PartyRole::Customer)->create();
        $draft = Invoice::factory()->sale()->draft()->create([
            'party_id' => $customer->id,
            'invoice_no' => 'S-DRAFT',
            'issued_on' => '2026-10-01',
            'due_on' => '2026-10-27',
            'subtotal' => 1500,
            'total' => 1500,
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.dashboard'))
            ->assertViewHas('invoiceReminders', function (array $reminders): bool {
                $overdue = collect($reminders['receivables']['tabs'])->firstWhere('key', 'overdue');

                return $overdue['count'] === 0;
            });

        $this->assertTrue(Cache::has(DashboardCache::PREFIX.DashboardCache::invoiceRemindersKey(today())));

        $this->actingAs($this->admin)
            ->post(route('admin.invoices.approve', $draft))
            ->assertRedirect(route('admin.invoices.show', $draft))
            ->assertSessionHas('success', 'Fatura onaylandı; cariye yazıldı.');

        $draft->refresh();
        $this->assertFalse($draft->isDraft());
        $this->assertSame('1500.00', app(Ledger::class)->live($draft)->sole()->debit);
        $this->assertFalse(Cache::has(DashboardCache::PREFIX.DashboardCache::invoiceRemindersKey(today())));
        $this->assertAuditPasses();

        $this->actingAs($this->admin)
            ->get(route('admin.dashboard'))
            ->assertViewHas('invoiceReminders', function (array $reminders) use ($draft): bool {
                $overdue = collect($reminders['receivables']['tabs'])->firstWhere('key', 'overdue');

                return $overdue['count'] === 1 && $overdue['items'][0]['id'] === $draft->id;
            });
    }

    public function test_draft_cannot_be_paid_matched_or_paid_at_entry(): void
    {
        $supplier = Party::factory()->company()->create();
        $cash = Bank::factory()->create(['type' => AccountType::Cash]);
        $draft = Invoice::factory()->draft()->create([
            'party_id' => $supplier->id,
            'subtotal' => 1000,
            'total' => 1000,
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.invoices.payments.store', $draft), [
                'amount' => '100',
                'occurred_on' => today()->toDateString(),
                'payment_method' => 'cash',
                'bank_id' => $cash->id,
            ])
            ->assertRedirect()
            ->assertSessionHas('error', 'Taslak fatura ödenemez; önce onaylayın.');

        $this->assertSame(0, $draft->payments()->count());
        $this->assertCount(0, app(Ledger::class)->live($draft));

        $this->actingAs($this->admin)
            ->post(route('admin.invoices.store'), [
                ...$this->payload($supplier, 'A-PAID'),
                'is_draft' => '1',
                'payment_status' => 'paid',
                'payment' => [
                    'payment_method' => 'cash',
                    'bank_id' => $cash->id,
                ],
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Taslak fatura kaydedildi.');

        $saved = Invoice::query()->where('invoice_no', 'A-PAID')->sole();
        $this->assertTrue($saved->isDraft());
        $this->assertSame(0, $saved->payments()->count());
        $this->assertCount(0, app(Ledger::class)->live($saved));
    }

    public function test_editing_a_draft_keeps_it_draft_without_ledger(): void
    {
        $draft = Invoice::factory()->draft()->create(['invoice_no' => 'A-DRAFT', 'subtotal' => 1000, 'total' => 1000]);

        $this->actingAs($this->admin)
            ->put(route('admin.invoices.update', $draft), $this->payload($draft->party, 'A-DRAFT', [
                ['description' => 'Kum', 'quantity' => '2', 'unit_price' => '100'],
            ]))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Taslak fatura güncellendi.');

        $draft->refresh();
        $this->assertTrue($draft->isDraft());
        $this->assertSame('200.00', $draft->total);
        $this->assertCount(0, app(Ledger::class)->live($draft));
        $this->assertAuditPasses();
    }

    public function test_deleting_a_draft_skips_ledger_void(): void
    {
        $draft = Invoice::factory()->draft()->create(['subtotal' => 500, 'total' => 500]);

        $this->actingAs($this->admin)
            ->delete(route('admin.invoices.destroy', $draft))
            ->assertRedirect(route('admin.invoices.index'))
            ->assertSessionHas('success', 'Taslak fatura silindi.');

        $this->assertSoftDeleted($draft);
        $this->assertCount(0, app(Ledger::class)->live($draft));
        $this->assertAuditPasses();
    }

    public function test_draft_shares_invoice_number_uniqueness_with_live_invoices(): void
    {
        $party = Party::factory()->company()->create();
        Invoice::factory()->create(['party_id' => $party->id, 'invoice_no' => 'A-1023', 'direction' => InvoiceDirection::Purchase]);

        $this->actingAs($this->admin)
            ->from(route('admin.invoices.create'))
            ->post(route('admin.invoices.store'), [...$this->payload($party), 'is_draft' => '1'])
            ->assertRedirect(route('admin.invoices.create'))
            ->assertSessionHasErrors(['invoice_no' => 'Bu carinin bu numarada bir faturası zaten var.']);

        Invoice::factory()->draft()->create([
            'party_id' => $party->id,
            'invoice_no' => 'A-DRAFT-NO',
            'direction' => InvoiceDirection::Purchase,
        ]);

        $this->actingAs($this->admin)
            ->from(route('admin.invoices.create'))
            ->post(route('admin.invoices.store'), [...$this->payload($party, 'A-DRAFT-NO'), 'is_draft' => '0'])
            ->assertRedirect(route('admin.invoices.create'))
            ->assertSessionHasErrors(['invoice_no']);
    }

    public function test_drafts_are_excluded_from_due_filters_reminders_and_aging(): void
    {
        $this->travelTo('2026-10-30 12:00:00');
        $customer = Party::factory()->company()->withRole(PartyRole::Customer)->create(['name' => 'Taslak Müşteri']);
        $draft = Invoice::factory()->sale()->draft()->create([
            'party_id' => $customer->id,
            'invoice_no' => 'S-HIDE',
            'issued_on' => '2026-10-01',
            'due_on' => '2026-10-27',
            'subtotal' => 900,
            'total' => 900,
        ]);
        $live = Invoice::factory()->sale()->create([
            'party_id' => $customer->id,
            'invoice_no' => 'S-SHOW',
            'issued_on' => '2026-10-01',
            'due_on' => '2026-10-28',
            'subtotal' => 400,
            'total' => 400,
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.invoices.index', ['f' => ['overdue' => '1']]))
            ->assertOk()
            ->assertSee('S-SHOW')
            ->assertDontSee('S-HIDE');

        $this->actingAs($this->admin)
            ->get(route('admin.invoices.index', ['f' => ['draft' => '1']]))
            ->assertOk()
            ->assertSee('S-HIDE')
            ->assertDontSee('S-SHOW');

        $this->actingAs($this->admin)
            ->get(route('admin.dashboard'))
            ->assertViewHas('invoiceReminders', function (array $reminders) use ($live, $draft): bool {
                $overdue = collect($reminders['receivables']['tabs'])->firstWhere('key', 'overdue');

                return $overdue['count'] === 1
                    && $overdue['items'][0]['id'] === $live->id
                    && collect($overdue['items'])->pluck('id')->doesntContain($draft->id);
            });

        $report = app(InvoiceAgingReportService::class)->report(['direction' => InvoiceDirection::Sale->value]);
        $this->assertSame(400.0, $report['totals']['total']);
        $this->assertSame(['Taslak Müşteri'], array_column($report['rows'], 'name'));
    }

    public function test_approve_requires_edit_invoice(): void
    {
        $viewer = User::factory()->create();
        $viewer->givePermissionTo(['view-dashboard', 'view-invoice']);
        $draft = Invoice::factory()->draft()->create();

        $this->actingAs($viewer)
            ->post(route('admin.invoices.approve', $draft))
            ->assertForbidden();

        $this->assertTrue($draft->fresh()->isDraft());
    }

    public function test_create_form_offers_draft_and_live_submit_buttons(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.invoices.create'))
            ->assertOk()
            ->assertSee('name="is_draft" value="0"', false)
            ->assertSee('name="is_draft" value="1"', false)
            ->assertSee('Taslak kaydet', false);
    }

    /**
     * @param  list<array<string, string>>|null  $lines
     * @return array<string, mixed>
     */
    private function payload(Party $party, string $invoiceNo = 'A-1023', ?array $lines = null): array
    {
        return [
            'direction' => 'purchase',
            'party_id' => $party->id,
            'invoice_no' => $invoiceNo,
            'issued_on' => today()->toDateString(),
            'lines' => $lines ?? [['description' => 'Çimento', 'quantity' => '10', 'unit_price' => '250', 'vat_rate' => '0']],
        ];
    }

    private function assertAuditPasses(): void
    {
        $this->artisan('ledger:audit')
            ->expectsOutputToContain('Cari, belgeler ve raporlar birebir tutuyor.')
            ->assertSuccessful();
    }
}
