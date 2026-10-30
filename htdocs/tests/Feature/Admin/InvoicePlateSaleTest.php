<?php

namespace Tests\Feature\Admin;

use App\Models\Invoice;
use App\Models\LedgerEntry;
use App\Models\Party;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\PartyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoicePlateSaleTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Party $owner;

    private Vehicle $vehicle;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-06-20 12:00:00');
        $this->admin = User::factory()->yonetici()->create();
        $this->owner = Party::factory()->create();
        $this->vehicle = Vehicle::factory()->ownedBy($this->owner, '2026-01-01')->create(['plate' => '34 PL 280']);
    }

    public function test_plate_sale_debits_the_owner_on_that_day_with_the_vehicle(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.invoices.store'), $this->payload())
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.invoices.index'));

        $invoice = Invoice::query()->sole();
        $this->assertSame($this->owner->id, $invoice->party_id);
        $this->assertSame($this->vehicle->id, $invoice->vehicle_id);
        $this->assertSame('sale', $invoice->direction->value);

        $entry = LedgerEntry::query()->where('source_type', 'invoice')->sole();
        $this->assertSame($this->owner->id, $entry->party_id);
        $this->assertSame($this->vehicle->id, $entry->vehicle_id);
        $this->assertSame('1500.00', $entry->debit);
        $this->assertSame('0.00', $entry->credit);
    }

    public function test_plate_sale_to_temporary_cari_is_allowed_and_tied_to_the_plate(): void
    {
        $temporary = app(PartyService::class)->temporaryParty();
        $plate = Vehicle::factory()->ownedBy($temporary, '2026-03-01')->create(['plate' => '06 GC 001']);

        $this->actingAs($this->admin)
            ->post(route('admin.invoices.store'), $this->payload([
                'plate' => '06 GC 001',
                'invoice_no' => 'S-TMP-1',
            ]))
            ->assertSessionHasNoErrors();

        $invoice = Invoice::query()->where('invoice_no', 'S-TMP-1')->sole();
        $this->assertSame($temporary->id, $invoice->party_id);
        $this->assertSame($plate->id, $invoice->vehicle_id);
        $this->assertSame($plate->id, LedgerEntry::query()->where('source_type', 'invoice')->sole()->vehicle_id);
    }

    public function test_closed_ownership_period_rejects_a_plate_sale(): void
    {
        $buyer = Party::factory()->create();

        $this->actingAs($this->admin)
            ->post(route('admin.vehicles.ownerships.store', $this->vehicle), [
                'party_id' => $buyer->id,
                'started_on' => '2026-06-05',
                'settlement' => 'settled',
            ])
            ->assertSessionHas('success');

        $this->actingAs($this->admin)
            ->post(route('admin.invoices.store'), $this->payload([
                'issued_on' => '2026-06-01',
                'invoice_no' => 'S-CLOSED',
            ]))
            ->assertSessionHas('error');

        $this->assertSame(0, Invoice::query()->count());
        $this->assertSame(0, LedgerEntry::query()->where('source_type', 'invoice')->count());
    }

    public function test_reassigning_temporary_plate_moves_its_sale_invoices(): void
    {
        $temporary = app(PartyService::class)->temporaryParty();
        $plate = Vehicle::factory()->ownedBy($temporary, '2026-03-01')->create(['plate' => '06 GC 002']);

        $this->actingAs($this->admin)
            ->post(route('admin.invoices.store'), $this->payload([
                'plate' => '06 GC 002',
                'invoice_no' => 'S-MOVE',
                'issued_on' => '2026-03-10',
            ]))
            ->assertSessionHasNoErrors();

        $owner = Party::factory()->create();

        $this->actingAs($this->admin)
            ->post(route('admin.vehicles.ownerships.reassign', $plate), ['party_id' => $owner->id])
            ->assertSessionHas('success');

        $invoice = Invoice::query()->where('invoice_no', 'S-MOVE')->sole();
        $this->assertSame($owner->id, $invoice->party_id);
        $this->assertSame($owner->id, LedgerEntry::query()
            ->where('source_type', 'invoice')
            ->where('source_id', $invoice->id)
            ->whereNull('reversal_of')
            ->whereDoesntHave('reversal')
            ->sole()
            ->party_id);
        $this->assertSame(0.0, $this->balance($temporary));
        $this->assertSame(1500.0, $this->balance($owner));
    }

    public function test_transfer_carries_prior_plate_sale_and_moves_later_ones(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.invoices.store'), $this->payload([
                'invoice_no' => 'S-PRIOR',
                'issued_on' => '2026-06-01',
            ]))
            ->assertSessionHasNoErrors();

        $this->actingAs($this->admin)
            ->post(route('admin.invoices.store'), $this->payload([
                'invoice_no' => 'S-LATER',
                'issued_on' => '2026-06-10',
                'lines' => [[
                    'description' => 'Malzeme',
                    'quantity' => '3',
                    'unit_price' => '100',
                    'vat_rate' => '0',
                ]],
            ]))
            ->assertSessionHasNoErrors();

        $buyer = Party::factory()->create();

        $this->actingAs($this->admin)
            ->post(route('admin.vehicles.ownerships.store', $this->vehicle), [
                'party_id' => $buyer->id,
                'started_on' => '2026-06-05',
                'settlement' => 'carry',
            ])
            ->assertSessionHas('success');

        $prior = Invoice::query()->where('invoice_no', 'S-PRIOR')->sole();
        $later = Invoice::query()->where('invoice_no', 'S-LATER')->sole();
        $ownership = $this->vehicle->fresh()->currentOwnership;

        $this->assertSame($this->owner->id, $prior->party_id);
        $this->assertSame($buyer->id, $later->party_id);
        $this->assertSame($this->owner->id, $ownership->carried_from_party_id);
        $this->assertSame('-1500.00', $ownership->carried_balance);
        $this->assertSame(0.0, $this->balance($this->owner));
        $this->assertSame(1800.0, $this->balance($buyer), 'Devralınan 1.500 + kendi plaka satışı 300.');
    }

    public function test_settled_transfer_rejects_outstanding_plate_sale_balance(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.invoices.store'), $this->payload([
                'invoice_no' => 'S-OPEN',
                'issued_on' => '2026-06-01',
            ]))
            ->assertSessionHasNoErrors();

        $buyer = Party::factory()->create();

        $this->actingAs($this->admin)
            ->post(route('admin.vehicles.ownerships.store', $this->vehicle), [
                'party_id' => $buyer->id,
                'started_on' => '2026-06-05',
                'settlement' => 'settled',
            ])
            ->assertSessionHas('error');

        $this->assertSame(1, $this->vehicle->ownerships()->count());
        $this->assertSame($this->owner->id, Invoice::query()->where('invoice_no', 'S-OPEN')->sole()->party_id);
    }

    public function test_transferring_the_vehicle_moves_later_plate_sales_when_settled(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.invoices.store'), $this->payload([
                'invoice_no' => 'S-AFTER',
                'issued_on' => '2026-06-10',
            ]))
            ->assertSessionHasNoErrors();

        $buyer = Party::factory()->create();

        $this->actingAs($this->admin)
            ->post(route('admin.vehicles.ownerships.store', $this->vehicle), [
                'party_id' => $buyer->id,
                'started_on' => '2026-06-05',
                'settlement' => 'settled',
            ])
            ->assertSessionHas('success');

        $invoice = Invoice::query()->where('invoice_no', 'S-AFTER')->sole();
        $this->assertSame($buyer->id, $invoice->party_id);
        $this->assertSame($buyer->id, LedgerEntry::query()
            ->where('source_type', 'invoice')
            ->where('source_id', $invoice->id)
            ->whereNull('reversal_of')
            ->whereDoesntHave('reversal')
            ->sole()
            ->party_id);
        $this->assertSame(0.0, $this->balance($this->owner));
        $this->assertSame(1500.0, $this->balance($buyer));
    }

    public function test_owner_card_and_hakedis_link_plate_sale_material_to_the_invoice(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.invoices.store'), $this->payload([
                'invoice_no' => 'S-LINK',
                'issued_on' => '2026-06-12',
            ]))
            ->assertSessionHasNoErrors();

        $invoice = Invoice::query()->where('invoice_no', 'S-LINK')->sole();
        $href = route('admin.invoices.show', $invoice);

        $this->actingAs($this->admin)
            ->get(route('admin.parties.show', $this->owner))
            ->assertOk()
            ->assertSee('<a href="'.$href.'">Satış faturası S-LINK</a>', false);

        $this->actingAs($this->admin)
            ->get(route('admin.reports.vehicle-monthly', [
                'plate' => '34 PL 280',
                'year' => 2026,
                'month' => 6,
            ]))
            ->assertOk()
            ->assertSee('<a href="'.$href.'">Malzeme · S-LINK</a>', false);

        $this->actingAs($this->admin)
            ->get(route('admin.reports.owner-monthly', [
                'owner' => $this->owner->id,
                'year' => 2026,
                'month' => 6,
            ]))
            ->assertOk()
            ->assertSee('<a href="'.$href.'">Malzeme · S-LINK</a>', false);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'direction' => 'sale',
            'recipient' => 'plate',
            'plate' => '34 PL 280',
            'invoice_no' => 'S-280',
            'issued_on' => '2026-06-10',
            'is_draft' => false,
            'payment_status' => 'unpaid',
            'lines' => [[
                'description' => 'Malzeme',
                'quantity' => '10',
                'unit_price' => '150',
                'vat_rate' => '0',
            ]],
            ...$overrides,
        ];
    }

    private function balance(Party $party): float
    {
        return (float) LedgerEntry::query()
            ->where('party_id', $party->id)
            ->selectRaw('COALESCE(SUM(debit - credit), 0) AS balance')
            ->value('balance');
    }
}
