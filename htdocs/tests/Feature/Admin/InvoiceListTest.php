<?php

namespace Tests\Feature\Admin;

use App\Enums\InvoiceDirection;
use App\Enums\InvoicePaymentStatus;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Party;
use App\Models\User;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class InvoiceListTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->yonetici()->create();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.invoices.index'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_invoice_list_and_menu_link_require_the_view_permission(): void
    {
        $viewer = User::factory()->create();
        $viewer->givePermissionTo(['view-dashboard', 'view-cari-entry']);

        $this->actingAs($viewer)
            ->get(route('admin.invoices.index'))
            ->assertForbidden();

        $this->actingAs($viewer)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee(route('admin.invoices.index'));

        $viewer->givePermissionTo('view-invoice');

        $this->actingAs($viewer->fresh())
            ->get(route('admin.dashboard'))
            ->assertSee(route('admin.invoices.index'));
    }

    public function test_list_shows_total_paid_remaining_due_date_and_status(): void
    {
        Invoice::factory()->create([
            'invoice_no' => 'A-1023',
            'issued_on' => '2026-10-01',
            'due_on' => '2026-10-15',
            'subtotal' => 1500,
        ]);
        Invoice::factory()->sale()->create(['invoice_no' => 'S-0007', 'subtotal' => 900]);

        $this->actingAs($this->admin)
            ->get(route('admin.invoices.index'))
            ->assertOk()
            ->assertSeeInOrder(['A-1023', 'Alış', '1.500,00 TL', '0,00 TL', '1.500,00 TL', '15.10.2026', 'Ödenmedi', 'Vadesi geçti'])
            ->assertSee('S-0007')
            ->assertSee('Tahsil edilmedi');
    }

    public function test_list_takes_paid_remaining_and_status_from_the_matched_payments(): void
    {
        $partial = Invoice::factory()->create(['invoice_no' => 'A-PARTIAL', 'issued_on' => '2026-10-02', 'due_on' => '2026-10-15', 'subtotal' => 1000]);
        InvoicePayment::factory()->for($partial)->create(['amount' => 400]);
        InvoicePayment::factory()->for($partial)->create(['amount' => 100]);
        $paid = Invoice::factory()->sale()->create(['invoice_no' => 'S-PAID', 'issued_on' => '2026-10-01', 'due_on' => '2026-10-15', 'subtotal' => 900]);
        InvoicePayment::factory()->for($paid)->create();

        $this->travelTo('2026-10-30');

        $response = $this->actingAs($this->admin)
            ->get(route('admin.invoices.index'))
            ->assertOk()
            ->assertSeeInOrder(['A-PARTIAL', '1.000,00 TL', '500,00 TL', '500,00 TL', 'Kısmen ödendi', 'Vadesi geçti', 'S-PAID', '900,00 TL', '900,00 TL', '0,00 TL', 'Tahsil edildi']);

        $this->assertSame(1, substr_count($response->getContent(), 'Vadesi geçti'), 'A paid invoice is not overdue.');
    }

    public function test_list_filters_by_direction_party_and_date(): void
    {
        $supplier = Party::factory()->company()->create();
        Invoice::factory()->create(['invoice_no' => 'A-IN-RANGE', 'party_id' => $supplier->id, 'issued_on' => '2026-10-10']);
        Invoice::factory()->create(['invoice_no' => 'A-TOO-EARLY', 'party_id' => $supplier->id, 'issued_on' => '2026-09-30']);
        Invoice::factory()->create(['invoice_no' => 'A-OTHER-PARTY', 'issued_on' => '2026-10-10']);
        Invoice::factory()->sale()->create(['invoice_no' => 'S-SALE', 'party_id' => $supplier->id, 'issued_on' => '2026-10-10']);

        $this->actingAs($this->admin)
            ->get(route('admin.invoices.index', ['f' => [
                'direction' => InvoiceDirection::Purchase->value,
                'party' => $supplier->id,
                'from' => '2026-10-01',
                'to' => '2026-10-31',
            ]]))
            ->assertOk()
            ->assertSee('A-IN-RANGE')
            ->assertDontSee('A-TOO-EARLY')
            ->assertDontSee('A-OTHER-PARTY')
            ->assertDontSee('S-SALE');
    }

    public function test_list_filters_overdue_this_week_and_this_month_dues(): void
    {
        $this->travelTo('2026-10-30 12:00:00');

        Invoice::factory()->create(['invoice_no' => 'A-OVERDUE', 'issued_on' => '2026-10-01', 'due_on' => '2026-10-20', 'subtotal' => 1000]);
        $paidOverdue = Invoice::factory()->create(['invoice_no' => 'A-PAID-OVERDUE', 'issued_on' => '2026-10-01', 'due_on' => '2026-10-15', 'subtotal' => 500]);
        InvoicePayment::factory()->for($paidOverdue)->create();
        Invoice::factory()->create(['invoice_no' => 'A-THIS-WEEK', 'issued_on' => '2026-10-01', 'due_on' => '2026-10-31', 'subtotal' => 800]);
        Invoice::factory()->create(['invoice_no' => 'A-NEXT-WEEK', 'issued_on' => '2026-10-01', 'due_on' => '2026-11-05', 'subtotal' => 700]);
        Invoice::factory()->create(['invoice_no' => 'A-THIS-MONTH', 'issued_on' => '2026-10-01', 'due_on' => '2026-10-10', 'subtotal' => 600]);
        Invoice::factory()->create(['invoice_no' => 'A-NEXT-MONTH', 'issued_on' => '2026-10-01', 'due_on' => '2026-11-15', 'subtotal' => 400]);

        $presets = collect(app(InvoiceService::class)->searchOptions()['due_presets'])->keyBy('key');

        $this->actingAs($this->admin)
            ->get(route('admin.invoices.index', ['f' => $presets['overdue']['filters']]))
            ->assertOk()
            ->assertSee('Vadesi geçen')
            ->assertSee('A-OVERDUE')
            ->assertSee('A-THIS-MONTH')
            ->assertDontSee('A-PAID-OVERDUE')
            ->assertDontSee('A-THIS-WEEK')
            ->assertDontSee('A-NEXT-WEEK');

        $this->actingAs($this->admin)
            ->get(route('admin.invoices.index', ['f' => $presets['week']['filters']]))
            ->assertOk()
            ->assertSee('Bu hafta')
            ->assertSee('A-THIS-WEEK')
            ->assertDontSee('A-OVERDUE')
            ->assertDontSee('A-THIS-MONTH')
            ->assertDontSee('A-NEXT-WEEK')
            ->assertDontSee('A-NEXT-MONTH');

        $this->actingAs($this->admin)
            ->get(route('admin.invoices.index', ['f' => $presets['month']['filters']]))
            ->assertOk()
            ->assertSee('Bu ay')
            ->assertSee('A-OVERDUE')
            ->assertSee('A-THIS-WEEK')
            ->assertSee('A-THIS-MONTH')
            ->assertDontSee('A-NEXT-WEEK')
            ->assertDontSee('A-NEXT-MONTH');
    }

    public function test_list_due_preset_chips_link_to_the_matching_filters(): void
    {
        $this->travelTo('2026-10-30 12:00:00');

        $presets = collect(app(InvoiceService::class)->searchOptions()['due_presets'])->keyBy('key');

        $this->actingAs($this->admin)
            ->get(route('admin.invoices.index'))
            ->assertOk()
            ->assertSee(route('admin.invoices.index', ['f' => $presets['overdue']['filters']]))
            ->assertSee(route('admin.invoices.index', ['f' => $presets['week']['filters']]))
            ->assertSee(route('admin.invoices.index', ['f' => $presets['month']['filters']]));
    }

    #[TestWith([1000.0, 0.0, InvoicePaymentStatus::Unpaid])]
    #[TestWith([1000.0, 400.0, InvoicePaymentStatus::Partial])]
    #[TestWith([1000.0, 1000.0, InvoicePaymentStatus::Paid])]
    public function test_payment_status_follows_the_paid_amount(float $total, float $paid, InvoicePaymentStatus $expected): void
    {
        $this->assertSame($expected, InvoicePaymentStatus::for($total, $paid));
    }
}
