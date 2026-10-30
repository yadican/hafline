<?php

namespace Tests\Feature\Admin;

use App\Cache\DashboardCache;
use App\Enums\AccountType;
use App\Enums\InvoiceDirection;
use App\Enums\PartyRole;
use App\Models\Bank;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Party;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class InvoiceReminderTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->yonetici()->create();
    }

    public function test_invoice_reminders_group_open_dues_into_receivables_and_payables_windows(): void
    {
        $this->travelTo('2026-10-30 12:00:00');

        $customer = Party::factory()->company()->withRole(PartyRole::Customer)->create(['name' => 'Alacak Müşterisi']);
        $supplier = Party::factory()->company()->withRole(PartyRole::Supplier)->create(['name' => 'Borç Tedarikçi']);

        $overdueSale = $this->invoice(InvoiceDirection::Sale, $customer, '2026-10-27', 1500);
        $this->invoice(InvoiceDirection::Sale, $customer, '2026-10-29', 900, paid: true);
        $this->invoice(InvoiceDirection::Sale, $customer, '2026-10-30', 300);
        $this->invoice(InvoiceDirection::Sale, $customer, '2026-11-05', 400);
        $this->invoice(InvoiceDirection::Sale, $customer, '2026-11-06', 500);
        $this->invoice(InvoiceDirection::Sale, $customer, null, 200);

        $overduePurchase = $this->invoice(InvoiceDirection::Purchase, $supplier, '2026-10-28', 800);
        $this->invoice(InvoiceDirection::Purchase, $supplier, '2026-11-02', 1200);
        $this->invoice(InvoiceDirection::Purchase, $supplier, '2026-11-20', 600);

        $this->actingAs($this->admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('invoiceReminders', function (array $reminders) use ($overdueSale, $overduePurchase): bool {
                $receivables = collect($reminders['receivables']['tabs'])->keyBy('key');
                $payables = collect($reminders['payables']['tabs'])->keyBy('key');

                $this->assertSame('overdue', $reminders['receivables']['active']);
                $this->assertSame('overdue', $reminders['payables']['active']);
                $this->assertSame(
                    ['overdue' => [1, 1500.0], 'week' => [2, 700.0], 'month' => [1, 500.0]],
                    $receivables->map(fn (array $tab): array => [$tab['count'], $tab['amount']])->all(),
                );
                $this->assertSame(
                    ['overdue' => [1, 800.0], 'week' => [1, 1200.0], 'month' => [1, 600.0]],
                    $payables->map(fn (array $tab): array => [$tab['count'], $tab['amount']])->all(),
                );
                $this->assertSame($overdueSale->id, $receivables['overdue']['items'][0]['id']);
                $this->assertSame('3 gün geçti', $receivables['overdue']['items'][0]['when']);
                $this->assertSame($overduePurchase->id, $payables['overdue']['items'][0]['id']);
                $this->assertSame(['Bugün', '6 gün kaldı'], array_column($receivables['week']['items'], 'when'));
                $this->assertSame(['direction' => 'sale', 'due_from' => '2026-11-06', 'due_to' => '2026-11-29'], $receivables['month']['filters']);

                return true;
            })
            ->assertSee('Alacaklar', false)
            ->assertSee('Borçlar', false)
            ->assertSee('Alacak Müşterisi', false)
            ->assertSee('Borç Tedarikçi', false)
            ->assertSee(route('admin.invoices.show', $overdueSale), false)
            ->assertSee(route('admin.invoices.index', ['f' => ['direction' => 'sale', 'overdue' => '1']]))
            ->assertSee(route('admin.invoices.index', ['f' => [
                'direction' => 'sale',
                'due_from' => '2026-11-06',
                'due_to' => '2026-11-29',
            ]]))
            ->assertSee('Hatırlatmayı kapat', false);
    }

    public function test_dismissing_an_invoice_reminder_drops_it_from_the_cached_panel(): void
    {
        $this->travelTo('2026-10-30 12:00:00');
        $customer = Party::factory()->company()->withRole(PartyRole::Customer)->create();
        $invoice = $this->invoice(InvoiceDirection::Sale, $customer, '2026-10-27', 1500);
        $overdueCount = fn (array $reminders): int => collect($reminders['receivables']['tabs'])->firstWhere('key', 'overdue')['count'];

        $this->actingAs($this->admin)
            ->get(route('admin.dashboard'))
            ->assertViewHas('invoiceReminders', fn (array $reminders): bool => $overdueCount($reminders) === 1);

        $this->assertTrue(Cache::has(DashboardCache::PREFIX.DashboardCache::invoiceRemindersKey(today())));

        $this->actingAs($this->admin)
            ->post(route('admin.invoices.reminder.dismiss', $invoice))
            ->assertRedirect(route('admin.dashboard'))
            ->assertSessionHas('success', 'Hatırlatma kapatıldı.');

        $this->assertNotNull($invoice->fresh()->reminder_dismissed_at);

        $this->actingAs($this->admin)
            ->get(route('admin.dashboard'))
            ->assertViewHas('invoiceReminders', fn (array $reminders): bool => $overdueCount($reminders) === 0)
            ->assertDontSee(route('admin.invoices.show', $invoice), false);
    }

    public function test_paying_an_invoice_drops_it_from_the_cached_invoice_reminders(): void
    {
        $this->travelTo('2026-10-30 12:00:00');
        $customer = Party::factory()->company()->withRole(PartyRole::Customer)->create();
        $invoice = $this->invoice(InvoiceDirection::Sale, $customer, '2026-10-27', 1500);
        $overdueCount = fn (array $reminders): int => collect($reminders['receivables']['tabs'])->firstWhere('key', 'overdue')['count'];

        $this->actingAs($this->admin)
            ->get(route('admin.dashboard'))
            ->assertViewHas('invoiceReminders', fn (array $reminders): bool => $overdueCount($reminders) === 1);

        $this->assertTrue(Cache::has(DashboardCache::PREFIX.DashboardCache::invoiceRemindersKey(today())));

        $this->actingAs($this->admin)
            ->post(route('admin.invoices.payments.store', $invoice), [
                'amount' => 1500,
                'occurred_on' => '2026-10-30',
                'payment_method' => 'cash',
                'bank_id' => Bank::factory()->create(['type' => AccountType::Cash])->id,
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $this->actingAs($this->admin)
            ->get(route('admin.dashboard'))
            ->assertViewHas('invoiceReminders', fn (array $reminders): bool => $overdueCount($reminders) === 0);
    }

    public function test_invoice_reminders_are_shown_only_to_users_who_can_view_invoices(): void
    {
        (new RolePermissionSeeder)->run();

        $user = User::factory()->create();
        $user->givePermissionTo(['view-dashboard', 'view-financial-instrument']);

        $this->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('invoiceReminders', null)
            ->assertDontSee('Alacaklar', false)
            ->assertDontSee('Borçlar', false);

        $user->givePermissionTo('view-invoice');

        $this->actingAs($user->fresh())
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('invoiceReminders', fn (?array $reminders): bool => $reminders !== null)
            ->assertSee('Alacaklar', false)
            ->assertSee('Borçlar', false);
    }

    public function test_dismissing_a_reminder_requires_edit_invoice(): void
    {
        (new RolePermissionSeeder)->run();

        $invoice = $this->invoice(
            InvoiceDirection::Sale,
            Party::factory()->company()->withRole(PartyRole::Customer)->create(),
            today()->subDays(2)->toDateString(),
            500,
        );

        $user = User::factory()->create();
        $user->givePermissionTo(['view-dashboard', 'view-invoice']);

        $this->actingAs($user)
            ->post(route('admin.invoices.reminder.dismiss', $invoice))
            ->assertForbidden();

        $this->assertNull($invoice->fresh()->reminder_dismissed_at);
    }

    private function invoice(InvoiceDirection $direction, Party $party, ?string $dueOn, float|int $total, bool $paid = false): Invoice
    {
        $invoice = Invoice::factory()
            ->state([
                'direction' => $direction,
                'party_id' => $party->id,
                'issued_on' => '2026-10-01',
                'due_on' => $dueOn,
                'subtotal' => $total,
                'vat_amount' => 0,
                'total' => $total,
            ])
            ->create();

        if ($paid) {
            InvoicePayment::factory()->create(['invoice_id' => $invoice->id, 'amount' => $total]);
        }

        return $invoice;
    }
}
