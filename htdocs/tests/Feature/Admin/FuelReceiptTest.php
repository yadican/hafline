<?php

namespace Tests\Feature\Admin;

use App\Models\FuelReceipt;
use App\Models\FuelStation;
use App\Models\LedgerEntry;
use App\Models\Party;
use App\Models\Site;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FuelReceiptTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Party $owner;

    private Vehicle $vehicle;

    private FuelStation $station;

    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->yonetici()->create();
        $this->owner = Party::factory()->create();
        $this->vehicle = Vehicle::factory()->ownedBy($this->owner, '2026-01-01')->create(['plate' => '34 YK 100']);
        $this->site = Site::factory()->create();
        $this->station = FuelStation::factory()->create(['unit_price' => 47.25]);
        $this->station->sites()->attach($this->site);
    }

    public function test_guest_is_redirected_from_the_fuel_receipt_page(): void
    {
        $this->get(route('admin.fuel-receipts.index'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_receipt_debits_the_owner_with_quantity_times_price(): void
    {
        $this->actingAs($this->admin)->get(route('admin.fuel-receipts.create'))->assertOk();

        $this->actingAs($this->admin)
            ->post(route('admin.fuel-receipts.store'), $this->payload(['quantity' => '80,5', 'unit_price' => '']))
            ->assertRedirect(route('admin.fuel-receipts.index'))
            ->assertSessionHas('success');

        $receipt = FuelReceipt::query()->sole();

        $this->assertSame('80.50', $receipt->quantity);
        $this->assertSame('47.2500', $receipt->unit_price);
        $this->assertSame('3803.63', $receipt->amount);
        $this->assertSame($this->owner->id, $receipt->party_id);
        $this->assertSame($this->station->party_id, $receipt->fuel_party_id);
        $this->assertSame($this->site->id, $receipt->site_id);

        $entry = LedgerEntry::query()->sole();
        $this->assertSame('fuel_receipt', $entry->source_type);
        $this->assertSame($this->owner->id, $entry->party_id);
        $this->assertSame('3803.63', $entry->debit);
        $this->assertSame('0.00', $entry->credit);
    }

    public function test_a_typed_price_overrides_the_station_price(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.fuel-receipts.store'), $this->payload(['unit_price' => '50', 'quantity' => '10']));

        $this->assertSame('50.0000', FuelReceipt::query()->sole()->unit_price);
        $this->assertSame('500.00', LedgerEntry::query()->sole()->debit);
    }

    public function test_a_site_outside_the_station_is_rejected(): void
    {
        $other = Site::factory()->create();

        $this->actingAs($this->admin)
            ->post(route('admin.fuel-receipts.store'), $this->payload(['site_id' => $other->id]))
            ->assertSessionHasErrors('site_id');

        $this->assertSame(0, FuelReceipt::query()->count());
    }

    public function test_updating_reposts_and_deleting_reverses_the_ledger(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.fuel-receipts.store'), $this->payload());
        $receipt = FuelReceipt::query()->sole();

        $this->actingAs($this->admin)
            ->put(route('admin.fuel-receipts.update', $receipt), $this->payload(['quantity' => '20', 'unit_price' => '40']))
            ->assertRedirect(route('admin.fuel-receipts.index'));

        $this->assertSame('800.00', $receipt->fresh()->amount);
        $this->assertSame(800.0, $this->balance());

        $this->actingAs($this->admin)
            ->delete(route('admin.fuel-receipts.destroy', $receipt));

        $this->assertSoftDeleted($receipt);
        $this->assertSame(0.0, $this->balance());
    }

    public function test_transferring_the_vehicle_moves_its_receipts(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.fuel-receipts.store'), $this->payload(['issued_on' => '2026-06-10', 'quantity' => '10', 'unit_price' => '40']));

        $buyer = Party::factory()->create();

        $this->actingAs($this->admin)
            ->post(route('admin.vehicles.ownerships.store', $this->vehicle), ['party_id' => $buyer->id, 'started_on' => '2026-06-05'])
            ->assertSessionHas('success');

        $this->assertSame($buyer->id, FuelReceipt::query()->sole()->party_id);
        $this->assertSame(0.0, $this->balance());
        $this->assertSame(400.0, $this->balance($buyer));
    }

    public function test_lookup_returns_the_station_price_and_sites(): void
    {
        $this->actingAs($this->admin)
            ->getJson(route('admin.fuel-receipts.lookups.station', $this->station))
            ->assertOk()
            ->assertJsonPath('unit_price', '47.2500')
            ->assertJsonPath('sites.0.id', $this->site->id);

        $this->actingAs($this->admin)
            ->getJson(route('admin.fuel-receipts.lookups.vehicles', ['q' => '34YK']))
            ->assertOk()
            ->assertJsonPath('0.value', '34 YK 100');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'ticket_no' => 'Y-100',
            'plate' => '34 YK 100',
            'issued_on' => '2026-06-10',
            'shift' => 'day',
            'fuel_station_id' => $this->station->id,
            'site_id' => $this->site->id,
            'quantity' => '40',
            'unit_price' => '',
            'km' => '120000',
            'received_by' => 'Ali',
            'haul_ticket_count' => '3',
            ...$overrides,
        ];
    }

    private function balance(?Party $party = null): float
    {
        return (float) LedgerEntry::query()
            ->where('party_id', ($party ?? $this->owner)->id)
            ->selectRaw('COALESCE(SUM(debit - credit), 0) AS balance')
            ->value('balance');
    }
}
