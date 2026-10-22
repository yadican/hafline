<?php

namespace Tests\Feature\Admin;

use App\Enums\PartyRole;
use App\Enums\PaymentMethod;
use App\Models\CompanyTransaction;
use App\Models\Party;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyBalanceReportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Party $supplier;

    private Party $otherSupplier;

    private Party $multiRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->yonetici()->create();
        $this->supplier = Party::factory()->company()->withRole(PartyRole::Supplier)->create([
            'name' => 'ALPHA TEDARİK',
        ]);
        $this->otherSupplier = Party::factory()->company()->withRole(PartyRole::Supplier)->create([
            'name' => 'BETA TEDARİK',
        ]);
        $this->multiRole = Party::factory()->company()->withRole(PartyRole::Supplier, PartyRole::Market)->create([
            'name' => 'ÇOK ROL A.Ş.',
        ]);
    }

    public function test_guest_is_redirected_from_the_company_balance_report(): void
    {
        $this->get(route('admin.reports.company-balance'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_report_page_shows_filters_without_running(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.reports.company-balance'))
            ->assertOk()
            ->assertSee('Firma bakiye', false)
            ->assertSee('Cari türü ve dönemi seçip Raporu Göster’e basın.', false)
            ->assertViewHas('ready', false);
    }

    public function test_report_requires_role(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.reports.company-balance', [
                'period' => 'month',
                'year' => 2026,
                'month' => 9,
            ]))
            ->assertRedirect(route('admin.reports.company-balance'))
            ->assertSessionHasErrors(['role']);
    }

    public function test_report_builds_period_totals_and_closing_balance(): void
    {
        $this->makeTx($this->supplier, PartyRole::Supplier, '2026-08-20', debit: 0, credit: 1000);
        $this->makeTx($this->supplier, PartyRole::Supplier, '2026-09-05', debit: 0, credit: 500);
        $this->makeTx($this->supplier, PartyRole::Supplier, '2026-09-10', debit: 200, credit: 0);
        $this->makeTx($this->otherSupplier, PartyRole::Supplier, '2026-09-08', debit: 0, credit: 300);
        $this->makeTx($this->supplier, PartyRole::Supplier, '2026-10-01', debit: 0, credit: 999);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.reports.company-balance', $this->filters()));

        $response->assertOk()
            ->assertSee('Firma Bakiye Raporu', false)
            ->assertSee('ALPHA TEDARİK', false)
            ->assertSee('BETA TEDARİK', false)
            ->assertSee('500,00', false)
            ->assertSee('200,00', false)
            ->assertSee('300,00', false);

        $report = $response->viewData('report');
        $byName = collect($report['rows'])->keyBy('name');

        $this->assertSame(500.0, $byName['ALPHA TEDARİK']['credit']);
        $this->assertSame(200.0, $byName['ALPHA TEDARİK']['debit']);
        $this->assertSame(-1300.0, $byName['ALPHA TEDARİK']['balance']);
        $this->assertSame(300.0, $byName['BETA TEDARİK']['credit']);
        $this->assertSame(0.0, $byName['BETA TEDARİK']['debit']);
        $this->assertSame(-300.0, $byName['BETA TEDARİK']['balance']);
    }

    public function test_soft_deleted_transactions_are_excluded(): void
    {
        $live = $this->makeTx($this->supplier, PartyRole::Supplier, '2026-09-05', debit: 0, credit: 400);
        $gone = $this->makeTx($this->supplier, PartyRole::Supplier, '2026-09-06', debit: 0, credit: 800);
        $gone->delete();

        $report = $this->actingAs($this->admin)
            ->get(route('admin.reports.company-balance', $this->filters()))
            ->assertOk()
            ->viewData('report');

        $this->assertCount(1, $report['rows']);
        $this->assertSame(400.0, $report['rows'][0]['credit']);
        $this->assertNotSame($live->id, $gone->id);
    }

    public function test_multi_role_party_balances_are_isolated_by_role(): void
    {
        $this->makeTx($this->multiRole, PartyRole::Supplier, '2026-09-05', debit: 0, credit: 1000);
        $this->makeTx($this->multiRole, PartyRole::Market, '2026-09-05', debit: 700, credit: 0);

        $supplier = $this->actingAs($this->admin)
            ->get(route('admin.reports.company-balance', $this->filters(PartyRole::Supplier)))
            ->viewData('report');

        $market = $this->actingAs($this->admin)
            ->get(route('admin.reports.company-balance', $this->filters(PartyRole::Market)))
            ->viewData('report');

        $this->assertSame(1000.0, $supplier['rows'][0]['credit']);
        $this->assertSame(-1000.0, $supplier['rows'][0]['balance']);
        $this->assertSame(700.0, $market['rows'][0]['debit']);
        $this->assertSame(700.0, $market['rows'][0]['balance']);
    }

    public function test_export_requires_role_and_streams_csv(): void
    {
        $this->makeTx($this->supplier, PartyRole::Supplier, '2026-09-05', debit: 0, credit: 250);

        $this->actingAs($this->admin)
            ->get(route('admin.reports.company-balance.export', [
                'period' => 'month',
                'year' => 2026,
                'month' => 9,
            ]))
            ->assertRedirect(route('admin.reports.company-balance'))
            ->assertSessionHasErrors(['role']);

        $csv = $this->actingAs($this->admin)
            ->get(route('admin.reports.company-balance.export', $this->filters()))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('Firma;Alacak;Borç;Bakiye', $csv);
        $this->assertStringContainsString('ALPHA TEDARİK', $csv);
        $this->assertStringContainsString('250,00', $csv);
    }

    /**
     * @return array{period: string, year: int, month: int, role: string}
     */
    private function filters(PartyRole $role = PartyRole::Supplier): array
    {
        return [
            'period' => 'month',
            'year' => 2026,
            'month' => 9,
            'role' => $role->value,
        ];
    }

    private function makeTx(
        Party $party,
        PartyRole $role,
        string $occurredOn,
        float $debit = 0,
        float $credit = 0,
    ): CompanyTransaction {
        $attrs = [
            'party_id' => $party->id,
            'role' => $role,
            'occurred_on' => $occurredOn,
            'debit' => $debit,
            'credit' => $credit,
            'payment_method' => PaymentMethod::Cash,
            'description' => 'Test',
        ];

        if ($role === PartyRole::Market) {
            $attrs['vehicle_id'] = null;
            $attrs['owner_party_id'] = null;
        }

        return CompanyTransaction::query()->create($attrs);
    }
}
