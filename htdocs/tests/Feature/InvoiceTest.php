<?php

namespace Tests\Feature;

use App\Enums\InvoiceDirection;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_factory_creates_an_invoice_with_a_line_matching_its_totals(): void
    {
        $invoice = Invoice::factory()->vat(20)->create(['subtotal' => 1000]);

        $line = $invoice->lines()->sole();
        $this->assertSame(InvoiceDirection::Purchase, $invoice->direction);
        $this->assertSame(['1000.00', '200.00', '1200.00'], [$invoice->subtotal, $invoice->vat_amount, $invoice->total]);
        $this->assertSame(['1000.00', '20.00', '200.00', '1200.00'], [$line->subtotal, $line->vat_rate, $line->vat_amount, $line->total]);
    }

    public function test_factory_recomputes_the_header_from_given_lines(): void
    {
        $invoice = Invoice::factory()
            ->sale()
            ->has(InvoiceLine::factory()->vat(20)->state(['quantity' => 2, 'unit_price' => 150]), 'lines')
            ->has(InvoiceLine::factory()->state(['quantity' => 3, 'unit_price' => 100, 'sort_order' => 1]), 'lines')
            ->create()
            ->fresh();

        $this->assertSame(InvoiceDirection::Sale, $invoice->direction);
        $this->assertCount(2, $invoice->lines);
        $this->assertSame(['600.00', '60.00', '660.00'], [$invoice->subtotal, $invoice->vat_amount, $invoice->total]);
    }

    public function test_rejects_the_same_invoice_number_twice_for_the_same_party_and_direction(): void
    {
        $invoice = Invoice::factory()->create(['invoice_no' => 'A-1023']);

        $this->expectException(QueryException::class);

        Invoice::factory()->for($invoice->party)->create(['invoice_no' => 'A-1023']);
    }

    public function test_allows_the_same_invoice_number_in_the_other_direction_or_after_deletion(): void
    {
        $invoice = Invoice::factory()->create(['invoice_no' => 'A-1023']);

        Invoice::factory()->sale()->for($invoice->party)->create(['invoice_no' => 'A-1023']);
        $invoice->delete();
        Invoice::factory()->for($invoice->party)->create(['invoice_no' => 'A-1023']);

        $this->assertSame(2, Invoice::query()->where('invoice_no', 'A-1023')->count());
    }
}
