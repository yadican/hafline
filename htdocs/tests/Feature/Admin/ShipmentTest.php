<?php

namespace Tests\Feature\Admin;

use App\Enums\PartyRole;
use App\Models\DeliveryAddress;
use App\Models\LedgerEntry;
use App\Models\Party;
use App\Models\Shipment;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShipmentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Party $carrier;

    private Party $customer;

    private Vehicle $truck;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->yonetici()->create();
        $this->carrier = Party::factory()->create();
        $this->customer = Party::factory()->company()->create(['name' => 'DOĞA PEYZAJ']);
        $this->truck = Vehicle::factory()->ownedBy($this->carrier, '2026-01-01')->create(['plate' => '34 NK 100']);
    }

    public function test_a_trip_debits_the_customer_and_credits_the_carrier_and_referrer_with_vat_and_withholding(): void
    {
        $referrer = Party::factory()->create();

        $this->actingAs($this->admin)->get(route('admin.shipments.create'))->assertOk();

        $this->actingAs($this->admin)
            ->post(route('admin.shipments.store'), $this->payload([
                'carrier_vat_rate' => '20',
                'carrier_withholding_rate' => '0.20',
                'commission_party_id' => $referrer->id,
                'commission_amount' => '500',
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.shipments.index'));

        $shipment = Shipment::query()->sole();
        $this->assertSame('5000.00', $shipment->carrier_amount);
        $this->assertSame('5800.00', $shipment->carrier_net_amount);
        $this->assertSame('15000.00', $shipment->sale_amount);
        $this->assertSame('17400.00', $shipment->customer_amount);
        $this->assertSame(9500.0, $shipment->profit());
        $this->assertSame('Kartal şantiyesi', $shipment->deliveryAddress->name);

        $this->assertSame(17400.0, $this->balance($this->customer));
        $this->assertSame(-5800.0, $this->balance($this->carrier));
        $this->assertSame(-500.0, $this->balance($referrer));
        $this->assertTrue($this->customer->fresh()->hasRole(PartyRole::Customer));
        $this->assertTrue($referrer->fresh()->hasRole(PartyRole::Commission));

        $this->actingAs($this->admin)->get(route('admin.shipments.index', ['f' => ['from' => '2026-03-01']]))->assertOk()->assertSee('34 NK 100');
        $this->actingAs($this->admin)->get(route('admin.shipments.show', $shipment))->assertOk()->assertSee('17.400,00 TL');
        $this->actingAs($this->admin)->get(route('admin.shipments.edit', $shipment))->assertOk()->assertSee('Kartal şantiyesi');
        $this->actingAs($this->admin)
            ->getJson(route('admin.shipments.lookups.addresses', ['party' => $this->customer->id]))
            ->assertExactJson(['Kartal şantiyesi']);

        $this->actingAs($this->admin)
            ->put(route('admin.shipments.update', $shipment), $this->payload())
            ->assertSessionHasNoErrors();

        $this->assertSame(0.0, $this->balance($referrer));
        $this->assertSame(-5000.0, $this->balance($this->carrier));
        $this->assertSame(1, DeliveryAddress::query()->count());

        $this->artisan('ledger:audit')
            ->expectsOutputToContain('Cari, belgeler ve raporlar birebir tutuyor.')
            ->assertSuccessful();

        $this->actingAs($this->admin)
            ->delete(route('admin.shipments.destroy', $shipment))
            ->assertSessionHas('success');

        $this->assertSame(0.0, $this->balance($this->customer));
        $this->assertSame(0.0, $this->balance($this->carrier));
    }

    public function test_the_plate_must_belong_to_the_chosen_carrier(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.shipments.store'), $this->payload(['party_id' => Party::factory()->withRole(PartyRole::VehicleOwner)->create()->id]))
            ->assertSessionHasErrors('plate');

        $this->assertSame(0, Shipment::query()->count());
    }

    public function test_plate_lookup_lists_only_the_carriers_plates(): void
    {
        Vehicle::factory()->ownedBy(Party::factory()->create(), '2026-01-01')->create(['plate' => '34 NK 200']);

        $this->actingAs($this->admin)
            ->getJson(route('admin.shipments.lookups.vehicles', ['q' => '34NK', 'party' => $this->carrier->id]))
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.value', '34 NK 100');
    }

    public function test_a_trip_moves_with_the_plate_when_ownership_changes(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.shipments.store'), $this->payload())
            ->assertSessionHasNoErrors();

        $newOwner = Party::factory()->create();

        $this->actingAs($this->admin)
            ->post(route('admin.vehicles.ownerships.store', $this->truck), ['party_id' => $newOwner->id, 'started_on' => '2026-03-05', 'settlement' => 'carry'])
            ->assertSessionHas('success');

        $this->assertSame($newOwner->id, Shipment::query()->sole()->party_id);
        $this->assertSame(-5000.0, $this->balance($newOwner));
        $this->assertSame(0.0, $this->balance($this->carrier));

        $this->artisan('ledger:audit')
            ->expectsOutputToContain('Cari, belgeler ve raporlar birebir tutuyor.')
            ->assertSuccessful();
    }

    /**
     * 25.000 kg at 200 TL/ton to the carrier and 600 TL/ton to the customer (VAT 20%, 2/10 withheld).
     *
     * @param  array<string, string>  $overrides
     * @return array<string, string>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'party_id' => $this->carrier->id,
            'plate' => '34 NK 100',
            'waybill_no' => 'IRS-1',
            'carrier_price' => '200',
            'carrier_vat_rate' => '0',
            'carrier_withholding_rate' => '0.00',
            'driver_name' => 'Hasan Çelik',
            'shipped_on' => '2026-03-10',
            'net_kg' => '25.000',
            'loading_place' => 'Şile kum ocağı',
            'customer_party_id' => $this->customer->id,
            'delivery_address' => 'Kartal şantiyesi',
            'sale_price' => '600',
            'vat_rate' => '20',
            'withholding_rate' => '0.20',
            ...$overrides,
        ];
    }

    /**
     * Positive: the party owes the company.
     */
    private function balance(Party $party): float
    {
        return round((float) LedgerEntry::query()->where('party_id', $party->id)->sum('debit')
            - (float) LedgerEntry::query()->where('party_id', $party->id)->sum('credit'), 2);
    }
}
