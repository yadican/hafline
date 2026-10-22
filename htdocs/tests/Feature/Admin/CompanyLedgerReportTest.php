<?php

namespace Tests\Feature\Admin;

use App\Enums\PartyRole;
use App\Enums\PaymentMethod;
use App\Models\CompanyTransaction;
use App\Models\FuelStation;
use App\Models\Party;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyLedgerReportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Party $supplier;

    private Party $otherSupplier;

    private Party $fuelFirm;

    private Party $marketFirm;

    private Party $owner;

    private Vehicle $vehicle;

    private FuelStation $station;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
        $this->supplier = Party::factory()->company()->withRole(PartyRole::Supplier)->create([
            'name' => 'ALPHA TEDARİK',
        ]);
        $this->otherSupplier = Party::factory()->company()->withRole(PartyRole::Supplier)->create([
            'name' => 'BETA TEDARİK',
        ]);
        $this->fuelFirm = Party::factory()->company()->withRole(PartyRole::Fuel)->create([
            'name' => 'PETROL A.Ş.',
        ]);
        $this->marketFirm = Party::factory()->company()->withRole(PartyRole::Market)->create([
            'name' => 'PİYASA LTD',
        ]);
        $this->owner = Party::factory()->create(['name' => 'Ahmet Yılmaz']);
        $this->vehicle = Vehicle::factory()->ownedBy($this->owner, '2026-01-01')->create([
            'plate' => '34 ABR 69',
        ]);
        $this->station = FuelStation::factory()->create([
            'name' => 'ALTUR',
            'unit_price' => 50.00,
            'party_id' => $this->fuelFirm->id,
        ]);
    }

    public function test_guest_is_redirected_from_the_company_ledger_report(): void
    {
        $this->get(route('admin.reports.company-ledger'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_report_page_shows_filters_without_running(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.reports.company-ledger'))
            ->assertOk()
            ->assertSee('Firma cari', false)
            ->assertSee('Cari türü ve dönemi seçip Raporu Göster’e basın.', false)
            ->assertViewHas('ready', false);
    }

    public function test_report_requires_role(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.reports.company-ledger', [
                'period' => 'month',
                'year' => 2026,
                'month' => 9,
            ]))
            ->assertRedirect(route('admin.reports.company-ledger'))
            ->assertSessionHasErrors(['role']);
    }

    public function test_party_must_match_selected_role(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.reports.company-ledger', [
                'period' => 'month',
                'year' => 2026,
                'month' => 9,
                'role' => PartyRole::Supplier->value,
                'party' => $this->fuelFirm->id,
            ]))
            ->assertRedirect(route('admin.reports.company-ledger'))
            ->assertSessionHasErrors(['party']);
    }

    public function test_single_party_shows_opening_running_balance_and_closing(): void
    {
        $this->makeTx($this->supplier, PartyRole::Supplier, '2026-08-15', debit: 0, credit: 1000);
        $this->makeTx($this->supplier, PartyRole::Supplier, '2026-09-05', debit: 0, credit: 400);
        $this->makeTx($this->supplier, PartyRole::Supplier, '2026-09-12', debit: 250, credit: 0);

        $report = $this->actingAs($this->admin)
            ->get(route('admin.reports.company-ledger', $this->filters(PartyRole::Supplier, $this->supplier->id)))
            ->assertOk()
            ->assertSee('Geçmiş Dönemler (Devir)', false)
            ->assertSee('ALPHA TEDARİK', false)
            ->viewData('report');

        $this->assertCount(1, $report['groups']);
        $group = $report['groups'][0];
        $this->assertSame(-1000.0, $group['opening']);
        $this->assertCount(2, $group['lines']);
        $this->assertSame(-1400.0, $group['lines'][0]['balance']);
        $this->assertSame(-1150.0, $group['lines'][1]['balance']);
        $this->assertSame(250.0, $group['total_debit']);
        $this->assertSame(400.0, $group['total_credit']);
        $this->assertSame(-1150.0, $group['closing']);
    }

    public function test_all_parties_mode_lists_multiple_firms(): void
    {
        $this->makeTx($this->supplier, PartyRole::Supplier, '2026-09-05', debit: 0, credit: 100);
        $this->makeTx($this->otherSupplier, PartyRole::Supplier, '2026-09-06', debit: 50, credit: 0);

        $report = $this->actingAs($this->admin)
            ->get(route('admin.reports.company-ledger', $this->filters(PartyRole::Supplier)))
            ->assertOk()
            ->assertSee('ALPHA TEDARİK', false)
            ->assertSee('BETA TEDARİK', false)
            ->viewData('report');

        $this->assertCount(2, $report['groups']);
        $this->assertTrue($report['show_party_column']);
    }

    public function test_soft_deleted_lines_are_excluded(): void
    {
        $this->makeTx($this->supplier, PartyRole::Supplier, '2026-09-05', debit: 0, credit: 100);
        $gone = $this->makeTx($this->supplier, PartyRole::Supplier, '2026-09-06', debit: 0, credit: 500);
        $gone->delete();

        $report = $this->actingAs($this->admin)
            ->get(route('admin.reports.company-ledger', $this->filters(PartyRole::Supplier, $this->supplier->id)))
            ->viewData('report');

        $this->assertCount(1, $report['groups'][0]['lines']);
        $this->assertSame(100.0, $report['groups'][0]['total_credit']);
    }

    public function test_fuel_role_shows_litre_columns(): void
    {
        CompanyTransaction::query()->create([
            'party_id' => $this->fuelFirm->id,
            'role' => PartyRole::Fuel,
            'occurred_on' => '2026-09-10',
            'debit' => 0,
            'credit' => 5000,
            'payment_method' => PaymentMethod::Cash,
            'fuel_station_id' => $this->station->id,
            'liters' => 100,
            'unit_price' => 50,
            'description' => 'Yakıt faturası',
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.reports.company-ledger', $this->filters(PartyRole::Fuel, $this->fuelFirm->id)))
            ->assertOk()
            ->assertSee('Litre', false)
            ->assertSee('ALTUR', false)
            ->assertSee('100,00', false);
    }

    public function test_market_role_shows_plate_columns(): void
    {
        CompanyTransaction::query()->create([
            'party_id' => $this->marketFirm->id,
            'role' => PartyRole::Market,
            'occurred_on' => '2026-09-10',
            'debit' => 350,
            'credit' => 0,
            'payment_method' => PaymentMethod::Cash,
            'vehicle_id' => $this->vehicle->id,
            'owner_party_id' => $this->owner->id,
            'description' => 'Piyasa',
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.reports.company-ledger', $this->filters(PartyRole::Market, $this->marketFirm->id)))
            ->assertOk()
            ->assertSee('Plaka', false)
            ->assertSee('34 ABR 69', false)
            ->assertSee('Ahmet Yılmaz', false);
    }

    public function test_export_requires_role_and_streams_csv(): void
    {
        $this->makeTx($this->supplier, PartyRole::Supplier, '2026-09-05', debit: 0, credit: 175);

        $this->actingAs($this->admin)
            ->get(route('admin.reports.company-ledger.export', [
                'period' => 'month',
                'year' => 2026,
                'month' => 9,
            ]))
            ->assertRedirect(route('admin.reports.company-ledger'))
            ->assertSessionHasErrors(['role']);

        $csv = $this->actingAs($this->admin)
            ->get(route('admin.reports.company-ledger.export', $this->filters(PartyRole::Supplier)))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('Firma;Tarih;', $csv);
        $this->assertStringContainsString('Borç;Alacak;Bakiye', $csv);
        $this->assertStringContainsString('ALPHA TEDARİK', $csv);
        $this->assertStringContainsString('175,00', $csv);
    }

    /**
     * @return array{period: string, year: int, month: int, role: string, party?: string}
     */
    private function filters(PartyRole $role, ?string $partyId = null): array
    {
        $filters = [
            'period' => 'month',
            'year' => 2026,
            'month' => 9,
            'role' => $role->value,
        ];

        if ($partyId !== null) {
            $filters['party'] = $partyId;
        }

        return $filters;
    }

    private function makeTx(
        Party $party,
        PartyRole $role,
        string $occurredOn,
        float $debit = 0,
        float $credit = 0,
    ): CompanyTransaction {
        return CompanyTransaction::query()->create([
            'party_id' => $party->id,
            'role' => $role,
            'occurred_on' => $occurredOn,
            'debit' => $debit,
            'credit' => $credit,
            'payment_method' => PaymentMethod::Cash,
            'description' => 'Test',
        ]);
    }
}
