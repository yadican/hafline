<?php

namespace Tests\Feature;

use App\Enums\PartyRole;
use App\Models\Bank;
use App\Models\Party;
use App\Models\Shipment;
use App\Models\Vehicle;
use App\Repositories\VehicleAccountRepository;
use App\Services\CariEntryService;
use App\Services\ShipmentService;
use App\Services\VehicleOwnershipService;
use Database\Seeders\LoadTest\Support\Progress;
use Database\Seeders\LoadTest\TransferStage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use ReflectionMethod;
use Tests\TestCase;

class LoadTestTransferSettlementTest extends TestCase
{
    use RefreshDatabase;

    public function test_settled_transfer_zeroes_the_seller_when_later_shipments_move_to_the_buyer(): void
    {
        $customer = Party::factory()->company()->withRole(PartyRole::Customer)->create();
        $seller = Party::factory()->company()->withRole(PartyRole::VehicleOwner)->create();
        $buyer = Party::factory()->company()->withRole(PartyRole::VehicleOwner)->create();
        $vehicle = Vehicle::factory()->ownedBy($seller, '2026-01-01')->create(['plate' => '34 DV 752']);
        Bank::factory()->create();

        $shipments = app(ShipmentService::class);
        $shipments->create($this->trip($vehicle, $customer, '2026-03-01', 'NK-ONCE'), null);
        $shipments->create($this->trip($vehicle, $customer, '2026-03-12', 'NK-SONRA'), null);

        $on = Carbon::parse('2026-03-10');
        $stage = new TransferStage(
            new Progress(null),
            app(VehicleOwnershipService::class),
            app(CariEntryService::class),
            app(VehicleAccountRepository::class),
        );

        (new ReflectionMethod(TransferStage::class, 'settleWithSeller'))->invoke($stage, $vehicle, $seller, $on);
        app(VehicleOwnershipService::class)->transfer($vehicle, $buyer, $on, false);

        $later = Shipment::query()->where('waybill_no', 'NK-SONRA')->sole();
        $moved = round((float) $later->carrier_net_amount + (float) $later->driver_amount, 2);
        $accounts = app(VehicleAccountRepository::class);

        $this->assertSame($buyer->id, $later->party_id);
        $this->assertSame(0.0, $accounts->balance($vehicle->id, $seller->id));
        $this->assertSame('0.00', $vehicle->fresh()->currentOwnership->carried_balance);
        $this->assertSame($moved, $accounts->balance($vehicle->id, $buyer->id));
    }

    /**
     * @return array<string, string>
     */
    private function trip(Vehicle $vehicle, Party $customer, string $on, string $waybill): array
    {
        return [
            'plate' => $vehicle->plate,
            'shipped_on' => $on,
            'net_kg' => '25000',
            'loading_place' => 'Şile kum ocağı',
            'delivery_address' => 'Kartal şantiyesi',
            'customer_party_id' => $customer->id,
            'carrier_price' => '400',
            'carrier_vat_rate' => '0',
            'carrier_withholding_rate' => '0',
            'sale_price' => '550',
            'vat_rate' => '0',
            'withholding_rate' => '0',
            'waybill_no' => $waybill,
            'driver_name' => 'Ali Veli',
            'driver_amount' => '1000',
        ];
    }
}
