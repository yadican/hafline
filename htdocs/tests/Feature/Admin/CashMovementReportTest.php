<?php

namespace Tests\Feature\Admin;

use App\Enums\PartyRole;
use App\Enums\PaymentMethod;
use App\Models\CashEntry;
use App\Models\CompanyTransaction;
use App\Models\LedgerEntry;
use App\Models\Party;
use App\Models\Site;
use App\Models\SiteTransaction;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehiclePayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CashMovementReportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Site $site;

    private Party $owner;

    private Vehicle $vehicle;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->yonetici()->create();
        $this->site = Site::factory()->create(['name' => 'BAKIRKÖY']);
        $this->owner = Party::factory()->create(['name' => 'Ahmet']);
        $this->vehicle = Vehicle::factory()->ownedBy($this->owner, '2026-01-01')->create([
            'plate' => '34 ABR 69',
        ]);
    }

    public function test_guest_is_redirected(): void
    {
        $this->get(route('admin.reports.cash-movements'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_page_shows_filters_without_running(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.reports.cash-movements'))
            ->assertOk()
            ->assertSee('Kasa hareketleri', false)
            ->assertViewHas('ready', false);
    }

    public function test_report_merges_cash_entry_site_and_ledger_with_running_balance(): void
    {
        CashEntry::query()->create([
            'debit' => 5000,
            'credit' => 0,
            'occurred_on' => '2026-08-20',
            'description' => 'Eski giriş',
        ]);

        CashEntry::query()->create([
            'debit' => 1000,
            'credit' => 0,
            'occurred_on' => '2026-09-05',
            'description' => 'Nakit giriş',
        ]);

        SiteTransaction::query()->create([
            'site_id' => $this->site->id,
            'payment_method' => PaymentMethod::Cash,
            'debit' => 400,
            'credit' => 0,
            'occurred_on' => '2026-09-06',
            'description' => 'Şantiye nakit çıkış',
        ]);

        // Cheque must not appear
        SiteTransaction::query()->create([
            'site_id' => $this->site->id,
            'payment_method' => PaymentMethod::Cheque,
            'debit' => 999,
            'credit' => 0,
            'occurred_on' => '2026-09-07',
            'description' => 'Çek',
        ]);

        $payment = VehiclePayment::query()->create([
            'vehicle_id' => $this->vehicle->id,
            'party_id' => $this->owner->id,
            'payment_method' => PaymentMethod::Cash,
            'amount' => 250,
            'occurred_on' => '2026-09-08',
            'description' => 'Plaka nakit',
        ]);

        LedgerEntry::query()->create([
            'id' => (string) Str::uuid(),
            'party_id' => $this->owner->id,
            'occurred_on' => '2026-09-08',
            'debit' => 250,
            'credit' => 0,
            'description' => 'Plaka Ödeme · Plaka nakit',
            'source_type' => 'vehicle_payment',
            'source_id' => $payment->id,
            'vehicle_id' => $this->vehicle->id,
            'payment_method' => PaymentMethod::Cash,
        ]);

        $report = $this->actingAs($this->admin)
            ->get(route('admin.reports.cash-movements', $this->filters()))
            ->assertOk()
            ->assertSee('Kasa Hareketleri', false)
            ->assertSee('Kasa Nakit', false)
            ->assertSee('BAKIRKÖY Ödeme', false)
            ->assertSee('34 ABR 69 Plaka Ödeme', false)
            ->assertDontSee('Çek', false)
            ->viewData('report');

        $this->assertSame(5000.0, $report['opening']);
        $this->assertCount(3, $report['lines']);
        $this->assertSame(1000.0, $report['total_giris']);
        $this->assertSame(650.0, $report['total_cikis']);
        // 5000 + 1000 − 400 − 250 = 5350
        $this->assertSame(5350.0, $report['closing']);
    }

    public function test_market_linked_vehicle_payment_is_not_double_counted(): void
    {
        $linked = VehiclePayment::query()->create([
            'vehicle_id' => $this->vehicle->id,
            'party_id' => $this->owner->id,
            'payment_method' => PaymentMethod::Cash,
            'amount' => 100,
            'occurred_on' => '2026-09-10',
            'company_transaction_id' => null,
        ]);

        // Simulate linked market payment: set company_transaction_id via raw after create
        // (FK requires real CT; instead mark via update only if we have CT — skip by using flag path)
        // Create a free cash payment that should appear, and a ledger line with company_transaction_id on VP.
        $supplier = Party::factory()->company()->withRole(PartyRole::Market)->create();
        $ct = CompanyTransaction::query()->create([
            'party_id' => $supplier->id,
            'role' => PartyRole::Market,
            'occurred_on' => '2026-09-10',
            'debit' => 100,
            'credit' => 0,
            'payment_method' => PaymentMethod::Cash,
            'vehicle_id' => $this->vehicle->id,
            'owner_party_id' => $this->owner->id,
        ]);

        $linked->update(['company_transaction_id' => $ct->id]);

        LedgerEntry::query()->create([
            'id' => (string) Str::uuid(),
            'party_id' => $this->owner->id,
            'occurred_on' => '2026-09-10',
            'debit' => 100,
            'credit' => 0,
            'source_type' => 'vehicle_payment',
            'source_id' => $linked->id,
            'vehicle_id' => $this->vehicle->id,
            'payment_method' => PaymentMethod::Cash,
        ]);

        LedgerEntry::query()->create([
            'id' => (string) Str::uuid(),
            'party_id' => $supplier->id,
            'occurred_on' => '2026-09-10',
            'debit' => 100,
            'credit' => 0,
            'source_type' => 'company_transaction',
            'source_id' => $ct->id,
            'payment_method' => PaymentMethod::Cash,
        ]);

        $report = $this->actingAs($this->admin)
            ->get(route('admin.reports.cash-movements', $this->filters()))
            ->viewData('report');

        // Only company_transaction cash line (100 çıkış), not the linked vehicle_payment
        $this->assertSame(100.0, $report['total_cikis']);
        $this->assertCount(1, $report['lines']);
        $this->assertStringContainsString('Cari', $report['lines'][0]['kind_label']);
    }

    public function test_soft_deleted_cash_entry_excluded(): void
    {
        $gone = CashEntry::query()->create([
            'debit' => 800,
            'credit' => 0,
            'occurred_on' => '2026-09-05',
        ]);
        $gone->delete();

        CashEntry::query()->create([
            'debit' => 200,
            'credit' => 0,
            'occurred_on' => '2026-09-06',
        ]);

        $report = $this->actingAs($this->admin)
            ->get(route('admin.reports.cash-movements', $this->filters()))
            ->viewData('report');

        $this->assertSame(200.0, $report['total_giris']);
        $this->assertCount(1, $report['lines']);
    }

    public function test_csv_export(): void
    {
        CashEntry::query()->create([
            'debit' => 125,
            'credit' => 0,
            'occurred_on' => '2026-09-05',
            'description' => 'Test',
        ]);

        $csv = $this->actingAs($this->admin)
            ->get(route('admin.reports.cash-movements.export', $this->filters()))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('Giriş;Çıkış;Bakiye', $csv);
        $this->assertStringContainsString('125,00', $csv);
    }

    /**
     * @return array{period: string, year: int, month: int}
     */
    private function filters(): array
    {
        return [
            'period' => 'month',
            'year' => 2026,
            'month' => 9,
        ];
    }
}
