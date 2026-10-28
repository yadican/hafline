<?php

namespace Tests\Feature\Admin;

use App\Enums\PartyRole;
use App\Models\DeliveryAddress;
use App\Models\LedgerEntry;
use App\Models\Material;
use App\Models\Party;
use App\Models\Quarry;
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

    private Vehicle $truck;

    private Quarry $quarry;

    private Material $sand;

    private DeliveryAddress $address;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->yonetici()->create();
        $this->carrier = Party::factory()->create();
        $this->truck = Vehicle::factory()->ownedBy($this->carrier, '2026-01-01')->create(['plate' => '34 NK 100']);
        $this->quarry = Quarry::factory()->create();
        $this->sand = Material::factory()->create(['name' => 'DERE KUMU']);
        $this->address = DeliveryAddress::factory()->create(['name' => 'TUZLA BETON SANTRALİ']);

        $this->price(['kind' => 'quarry', 'quarry_id' => $this->quarry->id, 'material_id' => $this->sand->id, 'price' => '300', 'valid_from' => '2026-01-01']);
        $this->price(['kind' => 'freight', 'quarry_id' => $this->quarry->id, 'delivery_address_id' => $this->address->id, 'price' => '200', 'valid_from' => '2026-01-01']);
        $this->price(['kind' => 'sale', 'delivery_address_id' => $this->address->id, 'material_id' => $this->sand->id, 'price' => '600', 'valid_from' => '2026-01-01']);
        $this->price(['kind' => 'sale', 'delivery_address_id' => $this->address->id, 'material_id' => $this->sand->id, 'price' => '650', 'valid_from' => '2026-03-01']);
    }

    public function test_a_material_sale_debits_the_customer_and_credits_carrier_quarry_and_commission(): void
    {
        $agent = Party::factory()->withRole(PartyRole::Commission)->create();

        $this->actingAs($this->admin)
            ->post(route('admin.shipments.store'), $this->payload(['commission_party_id' => $agent->id, 'commission_amount' => '500']))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.shipments.index'));

        $shipment = Shipment::query()->sole();
        $this->assertSame($this->carrier->id, $shipment->party_id);
        $this->assertSame($this->address->party_id, $shipment->customer_party_id);
        $this->assertSame('650.0000', $shipment->sale_price);
        $this->assertSame('3250.00', $shipment->vat_amount);
        $this->assertSame('650.00', $shipment->withholding_amount);
        $this->assertSame(3250.0, $shipment->profit());

        $this->assertSame(18850.0, $this->balance($this->address->party));
        $this->assertSame(-5000.0, $this->balance($this->carrier));
        $this->assertSame(-7500.0, $this->balance($this->quarry->party));
        $this->assertSame(-500.0, $this->balance($agent));
        $this->assertSame(['transport'], LedgerEntry::query()->where('source_type', 'shipment')->distinct()->pluck('module')->map->value->all());

        $this->actingAs($this->admin)->get(route('admin.shipments.index', ['f' => ['from' => '2026-03-01']]))->assertOk()->assertSee('34 NK 100');
        $this->actingAs($this->admin)->get(route('admin.shipments.show', $shipment))->assertOk()->assertSee('18.850,00 TL');
        $this->actingAs($this->admin)->get(route('admin.shipments.edit', $shipment))->assertOk();

        $this->actingAs($this->admin)
            ->put(route('admin.shipments.update', $shipment), $this->payload(['kind' => 'transport']))
            ->assertSessionHasNoErrors();

        $this->assertSame(0.0, $this->balance($this->quarry->party));
        $this->assertSame(0.0, $this->balance($agent));

        $this->artisan('ledger:audit')
            ->expectsOutputToContain('Cari, belgeler ve raporlar birebir tutuyor.')
            ->assertSuccessful();

        $this->actingAs($this->admin)
            ->delete(route('admin.shipments.destroy', $shipment))
            ->assertSessionHas('success');

        $this->assertSame(0.0, $this->balance($this->address->party));
        $this->assertSame(0.0, $this->balance($this->carrier));
    }

    public function test_price_lookup_follows_the_list_valid_on_the_date(): void
    {
        $this->actingAs($this->admin)
            ->getJson(route('admin.shipments.lookups.prices', [
                'kind' => 'material_sale',
                'quarry_id' => $this->quarry->id,
                'material_id' => $this->sand->id,
                'delivery_address_id' => $this->address->id,
                'shipped_on' => '2026-02-15',
            ]))
            ->assertOk()
            ->assertJson(['sale_price' => '600.0000', 'carrier_price' => '200.0000', 'quarry_price' => '300.0000']);
    }

    public function test_a_material_sale_needs_a_quarry_with_a_cari(): void
    {
        $place = Quarry::factory()->loadingPlace()->create();

        $this->actingAs($this->admin)
            ->post(route('admin.shipments.store'), $this->payload(['quarry_id' => $place->id, 'sale_price' => '600', 'carrier_price' => '200', 'quarry_price' => '300']))
            ->assertSessionHasErrors('quarry_id');

        $this->assertSame(0, Shipment::query()->count());
    }

    public function test_a_shipment_moves_with_the_plate_when_ownership_changes(): void
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

    public function test_transport_definitions_can_be_managed(): void
    {
        $customer = Party::factory()->create();
        $firm = Party::factory()->company()->create();

        $this->actingAs($this->admin)
            ->post(route('admin.materials.store'), ['name' => 'mıcır', 'unit' => 'm3', 'active' => '1'])
            ->assertRedirect(route('admin.materials.index'));
        $this->actingAs($this->admin)
            ->post(route('admin.quarries.store'), ['name' => 'Şile ocağı', 'party_id' => $firm->id, 'active' => '1'])
            ->assertRedirect(route('admin.quarries.index'));
        $this->actingAs($this->admin)
            ->post(route('admin.delivery-addresses.store'), ['party_id' => $customer->id, 'name' => 'Kartal şantiyesi', 'active' => '1'])
            ->assertRedirect(route('admin.delivery-addresses.index'));

        $this->assertTrue($firm->fresh()->hasRole(PartyRole::Quarry));
        $this->assertTrue($customer->fresh()->hasRole(PartyRole::Customer));

        foreach (['materials', 'quarries', 'delivery-addresses'] as $resource) {
            $this->actingAs($this->admin)->get(route("admin.{$resource}.index"))->assertOk();
            $this->actingAs($this->admin)->get(route("admin.{$resource}.create"))->assertOk();
        }

        $this->actingAs($this->admin)->get(route('admin.quarries.edit', $this->quarry))->assertOk()->assertSee('DERE KUMU');
        $this->actingAs($this->admin)->get(route('admin.delivery-addresses.edit', $this->address))->assertOk()->assertSee('650,0000');

        $this->actingAs($this->admin)
            ->post(route('admin.shipments.store'), $this->payload())
            ->assertSessionHasNoErrors();

        $this->actingAs($this->admin)
            ->delete(route('admin.materials.destroy', $this->sand))
            ->assertSessionHas('error');
    }

    /**
     * @param  array<string, string>  $data
     */
    private function price(array $data): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.transport-prices.store'), $data)
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');
    }

    /**
     * @param  array<string, string>  $overrides
     * @return array<string, string>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'kind' => 'material_sale',
            'shipped_on' => '2026-03-10',
            'plate' => '34 NK 100',
            'quarry_id' => $this->quarry->id,
            'material_id' => $this->sand->id,
            'delivery_address_id' => $this->address->id,
            'quantity' => '25',
            'sale_price' => '',
            'carrier_price' => '',
            'quarry_price' => '',
            'vat_rate' => '20',
            'withholding_rate' => '0.20',
            'waybill_no' => 'IRS-1',
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
