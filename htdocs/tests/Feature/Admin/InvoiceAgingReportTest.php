<?php

namespace Tests\Feature\Admin;

use App\Enums\InvoiceDirection;
use App\Enums\PartyRole;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Party;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Tests\TestCase;

class InvoiceAgingReportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Party $customerA;

    private Party $customerB;

    private Party $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-30 12:00:00');

        $this->admin = User::factory()->yonetici()->create();
        $this->customerA = Party::factory()->company()->withRole(PartyRole::Customer)->create([
            'name' => 'ALPHA MÜŞTERİ',
        ]);
        $this->customerB = Party::factory()->company()->withRole(PartyRole::Customer)->create([
            'name' => 'BETA MÜŞTERİ',
        ]);
        $this->supplier = Party::factory()->company()->withRole(PartyRole::Supplier)->create([
            'name' => 'GAMMA TEDARİK',
        ]);
    }

    public function test_guest_is_redirected_from_the_invoice_aging_report(): void
    {
        $this->get(route('admin.reports.invoice-aging'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_report_page_shows_filters_without_running(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.reports.invoice-aging'))
            ->assertOk()
            ->assertSee('Fatura yaşlandırma', false)
            ->assertSee('Alacaklar veya Borçlar seçip Rapor’a basın.', false)
            ->assertViewHas('ready', false);
    }

    public function test_report_requires_direction(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.reports.invoice-aging', ['x' => 1]))
            ->assertRedirect(route('admin.reports.invoice-aging'))
            ->assertSessionHasErrors(['direction']);
    }

    public function test_report_ages_remaining_balances_into_buckets_per_party(): void
    {
        $this->openSale($this->customerA, 1000, dueOn: null);
        $this->openSale($this->customerA, 200, dueOn: '2026-11-15');
        $this->openSale($this->customerA, 300, dueOn: '2026-10-30');
        $this->openSale($this->customerA, 400, dueOn: '2026-09-30');
        $this->openSale($this->customerA, 500, dueOn: '2026-09-29');
        $this->openSale($this->customerA, 600, dueOn: '2026-08-31');
        $this->openSale($this->customerA, 700, dueOn: '2026-08-30');
        $this->openSale($this->customerA, 800, dueOn: '2026-07-31');
        $this->openSale($this->customerB, 150, dueOn: '2026-10-01');
        $this->openPurchase($this->supplier, 999, dueOn: '2026-09-01');

        $paid = $this->openSale($this->customerA, 50, dueOn: '2026-09-01');
        InvoicePayment::factory()->create([
            'invoice_id' => $paid->id,
            'amount' => 50,
        ]);

        $report = $this->actingAs($this->admin)
            ->get(route('admin.reports.invoice-aging', $this->filters(InvoiceDirection::Sale)))
            ->assertOk()
            ->assertSee('Fatura Yaşlandırma Raporu', false)
            ->assertSee('ALPHA MÜŞTERİ', false)
            ->assertSee('BETA MÜŞTERİ', false)
            ->assertDontSee('GAMMA TEDARİK', false)
            ->viewData('report');

        $byName = collect($report['rows'])->keyBy('name');

        $this->assertSame(1200.0, $byName['ALPHA MÜŞTERİ']['current']);
        $this->assertSame(700.0, $byName['ALPHA MÜŞTERİ']['d0_30']);
        $this->assertSame(1100.0, $byName['ALPHA MÜŞTERİ']['d31_60']);
        $this->assertSame(700.0, $byName['ALPHA MÜŞTERİ']['d61_90']);
        $this->assertSame(800.0, $byName['ALPHA MÜŞTERİ']['d90_plus']);
        $this->assertSame(4500.0, $byName['ALPHA MÜŞTERİ']['total']);

        $this->assertSame(0.0, $byName['BETA MÜŞTERİ']['current']);
        $this->assertSame(150.0, $byName['BETA MÜŞTERİ']['d0_30']);
        $this->assertSame(150.0, $byName['BETA MÜŞTERİ']['total']);

        $this->assertSame(1200.0, $report['totals']['current']);
        $this->assertSame(850.0, $report['totals']['d0_30']);
        $this->assertSame(4650.0, $report['totals']['total']);
    }

    public function test_partial_payment_uses_remaining_and_soft_deleted_are_excluded(): void
    {
        $partial = $this->openSale($this->customerA, 1000, dueOn: '2026-10-01');
        InvoicePayment::factory()->create([
            'invoice_id' => $partial->id,
            'amount' => 250,
        ]);

        $gone = $this->openSale($this->customerA, 400, dueOn: '2026-09-01');
        $gone->delete();

        $live = $this->openSale($this->customerA, 100, dueOn: '2026-09-15');
        $deletedPayment = InvoicePayment::factory()->create([
            'invoice_id' => $live->id,
            'amount' => 100,
        ]);
        $deletedPayment->delete();

        $report = $this->actingAs($this->admin)
            ->get(route('admin.reports.invoice-aging', $this->filters(InvoiceDirection::Sale)))
            ->assertOk()
            ->viewData('report');

        $this->assertCount(1, $report['rows']);
        $this->assertSame(750.0, $report['rows'][0]['d0_30']);
        $this->assertSame(100.0, $report['rows'][0]['d31_60']);
        $this->assertSame(850.0, $report['rows'][0]['total']);
    }

    public function test_payables_direction_isolates_purchase_invoices(): void
    {
        $this->openSale($this->customerA, 500, dueOn: '2026-09-01');
        $this->openPurchase($this->supplier, 320, dueOn: '2026-08-01');

        $report = $this->actingAs($this->admin)
            ->get(route('admin.reports.invoice-aging', $this->filters(InvoiceDirection::Purchase)))
            ->assertOk()
            ->viewData('report');

        $this->assertCount(1, $report['rows']);
        $this->assertSame('GAMMA TEDARİK', $report['rows'][0]['name']);
        $this->assertSame(320.0, $report['rows'][0]['d61_90']);
        $this->assertSame('Borçlar', $report['direction_label']);
    }

    public function test_csv_export_streams_bucket_columns(): void
    {
        $this->openSale($this->customerA, 250, dueOn: '2026-10-15');

        $this->actingAs($this->admin)
            ->get(route('admin.reports.invoice-aging.export', [
                'format' => 'csv',
            ]))
            ->assertRedirect(route('admin.reports.invoice-aging'))
            ->assertSessionHasErrors(['direction']);

        $csv = $this->actingAs($this->admin)
            ->get(route('admin.reports.invoice-aging.export', [
                'format' => 'csv',
                ...$this->filters(InvoiceDirection::Sale),
            ]))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('Cari;"Vadesi gelmedi";"0–30 gün";"31–60 gün";"61–90 gün";"90+ gün";Toplam', $csv);
        $this->assertStringContainsString('ALPHA MÜŞTERİ', $csv);
        $this->assertStringContainsString('250,00', $csv);
    }

    public function test_excel_export_writes_numeric_amounts(): void
    {
        $this->openSale($this->customerA, 175, dueOn: '2026-10-15');

        $response = $this->actingAs($this->admin)
            ->get(route('admin.reports.invoice-aging.export', [
                'format' => 'xlsx',
                ...$this->filters(InvoiceDirection::Sale),
            ]))
            ->assertOk()
            ->assertDownload('fatura-yaslandirma-sale-2026-10-30.xlsx');

        $sheet = $this->loadSpreadsheet($response->streamedContent());
        $rows = collect($sheet->toArray(null, false, false, true));
        $headerRow = $rows->search(fn (array $row): bool => $row['A'] === 'Cari');
        $dataRow = $headerRow + 1;

        $this->assertSame('ALPHA MÜŞTERİ', $rows[$dataRow]['A']);
        $this->assertSame(DataType::TYPE_NUMERIC, $sheet->getCell("C{$dataRow}")->getDataType());
        $this->assertEquals(175, $rows[$dataRow]['C']);
        $this->assertEquals(175, $rows[$dataRow]['G']);
    }

    public function test_pdf_export_downloads_a_pdf_document(): void
    {
        $this->openSale($this->customerA, 100, dueOn: '2026-10-01');

        $response = $this->actingAs($this->admin)
            ->get(route('admin.reports.invoice-aging.export', [
                'format' => 'pdf',
                ...$this->filters(InvoiceDirection::Sale),
            ]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertDownload('fatura-yaslandirma-sale-2026-10-30.pdf');

        $this->assertStringStartsWith('%PDF-', (string) $response->getContent());
    }

    /**
     * @return array{direction: string}
     */
    private function filters(InvoiceDirection $direction): array
    {
        return [
            'direction' => $direction->value,
        ];
    }

    private function openSale(Party $party, float $total, ?string $dueOn): Invoice
    {
        return Invoice::factory()->sale()->create([
            'party_id' => $party->id,
            'subtotal' => $total,
            'vat_amount' => 0,
            'total' => $total,
            'due_on' => $dueOn,
        ]);
    }

    private function openPurchase(Party $party, float $total, ?string $dueOn): Invoice
    {
        return Invoice::factory()->create([
            'party_id' => $party->id,
            'direction' => InvoiceDirection::Purchase,
            'subtotal' => $total,
            'vat_amount' => 0,
            'total' => $total,
            'due_on' => $dueOn,
        ]);
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
}
