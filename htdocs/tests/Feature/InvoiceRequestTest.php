<?php

namespace Tests\Feature;

use App\Enums\CashDirection;
use App\Http\Requests\Admin\InvoiceRequest;
use App\Models\CashCategory;
use App\Models\Invoice;
use App\Models\Party;
use App\Services\PartyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Routing\Route;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InvoiceRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_drops_blank_lines_and_reads_comma_decimals(): void
    {
        $request = $this->resolve($this->data(lines: [
            ['description' => 'Çimento', 'quantity' => '2,5', 'unit_price' => '1000,50'],
            ['description' => ' ', 'quantity' => '', 'unit_price' => ''],
        ]));

        $this->assertSame([['description' => 'Çimento', 'quantity' => '2.5', 'unit_price' => '1000.50', 'vat_rate' => '0', 'cash_category_id' => null, 'stock_item_id' => null]], $request->validated('lines'));
    }

    public function test_requires_at_least_one_line_with_a_positive_quantity(): void
    {
        $this->assertSame(['lines'], $this->errors($this->data(lines: [['description' => '', 'quantity' => '', 'unit_price' => '']])));
        $this->assertSame(['lines.0.quantity'], $this->errors($this->data(lines: [['description' => 'Kum', 'quantity' => '0', 'unit_price' => '10']])));
    }

    public function test_due_date_cannot_be_before_the_invoice_date(): void
    {
        $this->assertSame(['due_on'], $this->errors($this->data(['issued_on' => '2026-03-10', 'due_on' => '2026-03-09'])));
        $this->assertSame([], $this->errors($this->data(['issued_on' => '2026-03-10', 'due_on' => '2026-03-10'])));
    }

    public function test_invoice_number_is_unique_per_party_and_direction_except_on_its_own_edit(): void
    {
        $invoice = Invoice::factory()->create(['invoice_no' => 'A-1023']);
        $data = $this->data(['party_id' => $invoice->party_id, 'invoice_no' => 'A-1023']);

        $this->assertSame(['invoice_no'], $this->errors($data));
        $this->assertSame([], $this->errors($data, $invoice));
        $this->assertSame([], $this->errors([...$data, 'direction' => 'sale']));
    }

    public function test_temporary_party_cannot_be_chosen(): void
    {
        $temporary = app(PartyService::class)->temporaryParty();

        $this->assertSame(['party_id'], $this->errors($this->data(['party_id' => $temporary->id])));
    }

    public function test_line_category_must_match_the_invoice_direction(): void
    {
        $income = CashCategory::factory()->create(['direction' => CashDirection::Income]);
        $both = CashCategory::factory()->create(['direction' => CashDirection::Both]);
        $line = fn (CashCategory $category): array => ['description' => 'Kum', 'quantity' => '1', 'unit_price' => '10', 'cash_category_id' => $category->id];

        $this->assertSame(['lines.0.cash_category_id'], $this->errors($this->data(lines: [$line($income)])));
        $this->assertSame([], $this->errors($this->data(['direction' => 'sale'], [$line($income)])));
        $this->assertSame([], $this->errors($this->data(lines: [$line($both)])));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @param  list<array<string, string>>|null  $lines
     * @return array<string, mixed>
     */
    private function data(array $overrides = [], ?array $lines = null): array
    {
        return [
            'direction' => 'purchase',
            'party_id' => Party::factory()->company()->create()->id,
            'invoice_no' => 'A-1',
            'issued_on' => '2026-03-10',
            'lines' => $lines ?? [['description' => 'Malzeme', 'quantity' => '1', 'unit_price' => '1000']],
            ...$overrides,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    private function errors(array $data, ?Invoice $invoice = null): array
    {
        try {
            $this->resolve($data, $invoice);
        } catch (ValidationException $exception) {
            return array_keys($exception->errors());
        }

        return [];
    }

    /**
     * Resolves the request as the store route (or the update route of the given invoice) would.
     *
     * @param  array<string, mixed>  $data
     */
    private function resolve(array $data, ?Invoice $invoice = null): InvoiceRequest
    {
        $request = InvoiceRequest::create('/admin/invoices', 'POST', $data);
        $request->setContainer($this->app)->setRedirector($this->app->make(Redirector::class));

        if ($invoice) {
            $route = (new Route('PUT', 'admin/invoices/{invoice}', []))->bind(Request::create("/admin/invoices/{$invoice->id}", 'PUT'));
            $route->setParameter('invoice', $invoice);
            $request->setRouteResolver(fn () => $route);
        }

        $request->validateResolved();

        return $request;
    }
}
