<?php

namespace Tests\Feature\Admin;

use App\Models\Haul;
use App\Models\Invoice;
use App\Models\Party;
use App\Models\Vehicle;
use App\Repositories\VehicleAccountRepository;
use App\Services\Ledger\HaulLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehicleAccountBalanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_plate_balance_deducts_sales_to_that_plate(): void
    {
        $owner = Party::factory()->create();
        $other = Party::factory()->create();
        $vehicle = Vehicle::factory()->ownedBy($owner, '2026-01-01')->create();

        $haul = Haul::factory()->create([
            'vehicle_id' => $vehicle->id,
            'party_id' => $owner->id,
            'hauled_at' => '2026-06-01 10:00:00',
            'price' => 1000,
            'dump_price' => 0,
        ]);
        app(HaulLedger::class)->sync($haul);

        Invoice::factory()->sale()->create([
            'party_id' => $owner->id,
            'vehicle_id' => $vehicle->id,
            'issued_on' => '2026-06-10',
            'subtotal' => 1500,
            'vat_amount' => 0,
            'total' => 1500,
            'is_draft' => false,
        ]);

        Invoice::factory()->sale()->draft()->create([
            'party_id' => $owner->id,
            'vehicle_id' => $vehicle->id,
            'issued_on' => '2026-06-11',
            'subtotal' => 400,
            'vat_amount' => 0,
            'total' => 400,
        ]);

        Invoice::factory()->sale()->create([
            'party_id' => $owner->id,
            'vehicle_id' => null,
            'issued_on' => '2026-06-12',
            'subtotal' => 700,
            'vat_amount' => 0,
            'total' => 700,
        ]);

        Invoice::factory()->sale()->create([
            'party_id' => $other->id,
            'vehicle_id' => $vehicle->id,
            'issued_on' => '2026-06-13',
            'subtotal' => 200,
            'vat_amount' => 0,
            'total' => 200,
        ]);

        $balance = app(VehicleAccountRepository::class)->balance($vehicle->id, $owner->id);

        $this->assertSame(-500.0, $balance);
    }
}
