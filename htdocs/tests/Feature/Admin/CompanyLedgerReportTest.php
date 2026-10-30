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
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
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

        $this->admin = User::factory()->yonetici()->create();
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
            ->assertSee('Cari türü, firma ve dönemi seçip Raporu Göster’e basın.', false)
            ->assertViewHas('ready', false);
    }

    public function test_report_requires_role_and_party(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.reports.company-ledger', [
                'period' => 'month',
                'year' => 2026,
                'month' => 9,
            ]))
            ->assertRedirect(route('admin.reports.company-ledger'))
            ->assertSessionHasErrors(['role', 'party']);

        $this->actingAs($this->admin)
            ->get(route('admin.reports.company-ledger', [
                'period' => 'month',
                'year' => 2026,
                'month' => 9,
                'role' => PartyRole::Supplier->value,
            ]))
            ->assertRedirect(route('admin.reports.company-ledger'))
            ->assertSessionHasErrors(['party']);
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

    public function test_report_is_scoped_to_the_selected_party_only(): void
    {
        $this->makeTx($this->supplier, PartyRole::Supplier, '2026-09-05', debit: 0, credit: 100);
        $this->makeTx($this->otherSupplier, PartyRole::Supplier, '2026-09-06', debit: 50, credit: 0);

        $report = $this->actingAs($this->admin)
            ->get(route('admin.reports.company-ledger', $this->filters(PartyRole::Supplier, $this->supplier->id)))
            ->assertOk()
            ->viewData('report');

        $this->assertCount(1, $report['groups']);
        $this->assertFalse($report['show_party_column']);
        $this->assertSame($this->supplier->id, $report['groups'][0]['party_id']);
        $this->assertSame('ALPHA TEDARİK', $report['groups'][0]['party_name']);
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

    public function test_export_requires_party_and_streams_csv(): void
    {
        $this->makeTx($this->supplier, PartyRole::Supplier, '2026-09-05', debit: 0, credit: 175);

        $this->actingAs($this->admin)
            ->get(route('admin.reports.company-ledger.export', [
                'format' => 'csv',
                'period' => 'month',
                'year' => 2026,
                'month' => 9,
                'role' => PartyRole::Supplier->value,
            ]))
            ->assertRedirect(route('admin.reports.company-ledger'))
            ->assertSessionHasErrors(['party']);

        $csv = $this->actingAs($this->admin)
            ->get(route('admin.reports.company-ledger.export', [
                'format' => 'csv',
                ...$this->filters(PartyRole::Supplier, $this->supplier->id),
            ]))
            ->assertOk()
            ->assertDownload('firma-cari-supplier-2026-09.csv')
            ->streamedContent();

        $this->assertStringContainsString('Tarih;"Belge No";Açıklama;Borç;Alacak;Bakiye', $csv);
        $this->assertStringContainsString('05.09.2026;;Test;;175,00;-175,00', $csv);
        $this->assertStringContainsString('"Toplam / Kapanış";;;0,00;175,00;-175,00', $csv);
    }

    public function test_excel_export_writes_numeric_amounts_and_running_balance(): void
    {
        $this->makeTx($this->supplier, PartyRole::Supplier, '2026-08-20', debit: 500);
        $this->makeTx($this->supplier, PartyRole::Supplier, '2026-09-05', credit: 175);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.reports.company-ledger.export', [
                'format' => 'xlsx',
                ...$this->filters(PartyRole::Supplier, $this->supplier->id),
            ]))
            ->assertOk()
            ->assertDownload('firma-cari-supplier-2026-09.xlsx');

        $sheet = $this->loadSpreadsheet($response->streamedContent());
        $rows = collect($sheet->toArray(null, false, false, true));

        $headerRow = $rows->search(fn (array $row): bool => $row['A'] === 'Tarih');
        $this->assertSame(['Tarih', 'Belge No', 'Açıklama', 'Borç', 'Alacak', 'Bakiye'], array_values($rows[$headerRow]));

        $openingRow = $rows->search(fn (array $row): bool => $row['C'] === 'Geçmiş Dönemler (Devir)');
        $this->assertEquals(500, $rows[$openingRow]['D']);
        $this->assertEquals(500, $rows[$openingRow]['F']);

        $lineRow = $openingRow + 1;
        $this->assertSame(DataType::TYPE_NUMERIC, $sheet->getCell("A{$lineRow}")->getDataType());
        $this->assertSame('2026-09-05', ExcelDate::excelToDateTimeObject($rows[$lineRow]['A'])->format('Y-m-d'));
        $this->assertSame(DataType::TYPE_NUMERIC, $sheet->getCell("E{$lineRow}")->getDataType());
        $this->assertEquals(175, $rows[$lineRow]['E']);
        $this->assertEquals(325, $rows[$lineRow]['F']);

        $totalRow = $rows->search(fn (array $row): bool => $row['A'] === 'Toplam / Kapanış');
        $this->assertEquals(0, $rows[$totalRow]['D']);
        $this->assertEquals(175, $rows[$totalRow]['E']);
        $this->assertEquals(325, $rows[$totalRow]['F']);
    }

    public function test_pdf_export_downloads_a_pdf_document(): void
    {
        $this->makeTx($this->supplier, PartyRole::Supplier, '2026-09-05', credit: 175);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.reports.company-ledger.export', [
                'format' => 'pdf',
                ...$this->filters(PartyRole::Supplier, $this->supplier->id),
            ]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertDownload('firma-cari-supplier-2026-09.pdf');

        $this->assertStringStartsWith('%PDF-', (string) $response->getContent());
    }

    public function test_export_rejects_unknown_formats(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.reports.company-ledger.export', [
                'format' => 'docx',
                ...$this->filters(PartyRole::Supplier, $this->supplier->id),
            ]))
            ->assertNotFound();
    }

    private function loadSpreadsheet(string $contents): Worksheet
    {
        $path = tempnam(sys_get_temp_dir(), 'report-xlsx');
        file_put_contents($path, $contents);

        try {
            return IOFactory::load($path)->getActiveSheet();
        } finally {
            unlink($path);
        }
    }

    /**
     * @return array{period: string, year: int, month: int, role: string, party: string}
     */
    private function filters(PartyRole $role, string $partyId): array
    {
        return [
            'period' => 'month',
            'year' => 2026,
            'month' => 9,
            'role' => $role->value,
            'party' => $partyId,
        ];
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
