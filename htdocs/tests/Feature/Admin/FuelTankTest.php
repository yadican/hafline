<?php

namespace Tests\Feature\Admin;

use App\Enums\FuelSourceType;
use App\Enums\PartyRole;
use App\Models\FuelReceipt;
use App\Models\FuelStation;
use App\Models\FuelTankFill;
use App\Models\LedgerEntry;
use App\Models\Party;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\FuelTankService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FuelTankTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Party $owner;

    private Party $supplier;

    private FuelStation $tank;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->yonetici()->create();
        $this->owner = Party::factory()->create();
        Vehicle::factory()->ownedBy($this->owner, '2026-01-01')->create(['plate' => '34 YK 100']);
        $this->supplier = Party::factory()->company()->withRole(PartyRole::Fuel)->create();
        $this->tank = FuelStation::factory()->tank()->create(['unit_price' => 50]);
    }

    public function test_fills_credit_the_supplier_and_receipts_draw_the_tank_at_average_cost(): void
    {
        $this->fill('2026-03-01', '1000', '40');
        $this->fill('2026-03-05', '1000', '46');

        $this->assertSame(-86000.0, $this->balance($this->supplier));

        $this->actingAs($this->admin)
            ->post(route('admin.fuel-receipts.store'), [
                'plate' => '34 YK 100',
                'issued_on' => '2026-03-10',
                'fuel_station_id' => $this->tank->id,
                'quantity' => '100',
                'unit_price' => '',
                'purchase_price' => '45',
            ])
            ->assertSessionHasNoErrors();

        $receipt = FuelReceipt::query()->sole();
        $this->assertNull($receipt->fuel_party_id);
        $this->assertSame('0.0000', $receipt->purchase_price);
        $this->assertSame(5000.0, $this->balance($this->owner));
        $this->assertSame(1, LedgerEntry::query()->where('source_type', 'fuel_receipt')->count());

        $tanks = app(FuelTankService::class);
        $this->assertSame(['quantity' => 1900.0, 'average_cost' => 43.0, 'value' => 81700.0], $tanks->stock($this->tank));

        $this->actingAs($this->admin)
            ->post(route('admin.fuel-stations.counts.store', $this->tank), ['counted_on' => '2026-03-11', 'quantity' => '1890'])
            ->assertSessionHas('success');

        $this->assertSame('-10.00', $this->tank->counts()->sole()->difference);
        $this->assertSame(['quantity' => 1890.0, 'average_cost' => 43.0, 'value' => 81270.0], $tanks->stock($this->tank));

        $this->actingAs($this->admin)
            ->get(route('admin.fuel-stations.show', $this->tank))
            ->assertOk()
            ->assertSee('1.890 lt');

        $this->actingAs($this->admin)
            ->delete(route('admin.fuel-stations.fills.destroy', [$this->tank, FuelTankFill::query()->where('unit_price', 40)->sole()]))
            ->assertSessionHas('success');

        $this->assertSame(-46000.0, $this->balance($this->supplier));

        $this->artisan('ledger:audit')
            ->expectsOutputToContain('Cari, belgeler ve raporlar birebir tutuyor.')
            ->assertSuccessful();
    }

    public function test_a_station_needs_its_firm_and_purchase_price_and_a_source_keeps_its_type(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.fuel-stations.store'), ['name' => 'YOL PETROL', 'type' => 'station', 'product' => 'diesel', 'unit_price' => '50', 'active' => '1'])
            ->assertSessionHasErrors(['party_id', 'purchase_price']);

        $this->actingAs($this->admin)
            ->post(route('admin.fuel-stations.store'), ['name' => 'MERKEZ DEPO', 'type' => 'tank', 'product' => 'diesel', 'party_id' => $this->supplier->id, 'unit_price' => '50', 'active' => '1'])
            ->assertSessionHasNoErrors();

        $tank = FuelStation::query()->where('name', 'MERKEZ DEPO')->sole();
        $this->assertSame(FuelSourceType::Tank, $tank->type);
        $this->assertNull($tank->party_id);

        $this->actingAs($this->admin)
            ->put(route('admin.fuel-stations.update', $tank), ['name' => 'MERKEZ DEPO', 'type' => 'station', 'product' => 'diesel', 'party_id' => $this->supplier->id, 'purchase_price' => '40', 'unit_price' => '50', 'active' => '1'])
            ->assertSessionHasNoErrors();

        $this->assertSame(FuelSourceType::Tank, $tank->fresh()->type);
        $this->assertNull($tank->fresh()->party_id);
    }

    public function test_only_our_own_tank_takes_fills(): void
    {
        $station = FuelStation::factory()->create();

        $this->actingAs($this->admin)
            ->post(route('admin.fuel-stations.fills.store', $station), ['party_id' => $this->supplier->id, 'filled_on' => '2026-03-01', 'quantity' => '100', 'unit_price' => '40'])
            ->assertSessionHas('error');

        $this->assertSame(0, FuelTankFill::query()->count());
    }

    private function fill(string $date, string $quantity, string $price): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.fuel-stations.fills.store', $this->tank), [
                'party_id' => $this->supplier->id,
                'filled_on' => $date,
                'quantity' => $quantity,
                'unit_price' => $price,
                'document_no' => 'D-'.$date,
            ])
            ->assertSessionHas('success');
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
