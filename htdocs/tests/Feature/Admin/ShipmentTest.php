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

    public function test_a_trip_posts_both_sides_with_vat_and_withholding_plus_driver_fee_and_referrer(): void
    {
        $referrer = Party::factory()->create();

        $this->actingAs($this->admin)->get(route('admin.shipments.create'))->assertOk();

        $this->actingAs($this->admin)
            ->post(route('admin.shipments.store'), $this->payload([
                'net_kg' => '70.000',
                'carrier_price' => '40000',
                'carrier_vat_rate' => '20',
                'carrier_withholding_rate' => '0.20',
                'sale_price' => '50000',
                'driver_name' => 'Ahmet Yılmaz',
                'driver_amount' => '2000',
                'commission_party_id' => $referrer->id,
                'commission_amount' => '7000',
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.shipments.index'));

        $shipment = Shipment::query()->sole();
        $this->assertSame('2800000.00', $shipment->carrier_amount);
        $this->assertSame('560000.00', $shipment->carrier_vat_amount);
        $this->assertSame('112000.00', $shipment->carrier_withholding_amount);
        $this->assertSame('3248000.00', $shipment->carrier_net_amount);
        $this->assertSame('3500000.00', $shipment->sale_amount);
        $this->assertSame('4060000.00', $shipment->customer_amount);
        $this->assertSame(691000.0, $shipment->profit());
        $this->assertSame('Kartal şantiyesi', $shipment->deliveryAddress->name);

        $this->assertSame(4060000.0, $this->balance($this->customer));
        $this->assertSame(-3250000.0, $this->balance($this->carrier));
        $this->assertSame(-7000.0, $this->balance($referrer));

        $driverFee = LedgerEntry::query()->where('party_id', $this->carrier->id)->where('credit', 2000)->sole();
        $this->assertSame($this->truck->id, $driverFee->vehicle_id);
        $this->assertStringStartsWith('Şoför ücreti · Ahmet Yılmaz · Sefer IRS-1', $driverFee->description);
        $this->assertTrue($this->customer->fresh()->hasRole(PartyRole::Customer));
        $this->assertTrue($referrer->fresh()->hasRole(PartyRole::Commission));

        $this->actingAs($this->admin)->get(route('admin.shipments.index', ['f' => ['from' => '2026-03-01']]))->assertOk()->assertSee('34 NK 100');
        $this->actingAs($this->admin)->get(route('admin.shipments.show', $shipment))->assertOk()->assertSee('4.060.000,00 TL');
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

    public function test_the_carrier_is_the_plate_owner_and_a_plate_without_one_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->getJson(route('admin.shipments.lookups.vehicles', ['q' => '34NK']))
            ->assertOk()
            ->assertJsonPath('0.value', '34 NK 100')
            ->assertJsonPath('0.meta', $this->carrier->name);

        $this->actingAs($this->admin)
            ->post(route('admin.shipments.store'), $this->payload(['shipped_on' => '2025-12-31']))
            ->assertSessionHasErrors('plate');

        $this->assertSame(0, Shipment::query()->count());
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
            'plate' => '34 NK 100',
            'waybill_no' => 'IRS-1',
            'carrier_price' => '200',
            'carrier_vat_rate' => '0',
            'carrier_withholding_rate' => '0.00',
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
