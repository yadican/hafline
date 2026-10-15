<?php

namespace Database\Seeders;

use App\Enums\Shift;
use App\Models\FuelReceipt;
use App\Models\FuelStation;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Ledger\FuelReceiptLedger;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Sample fuel receipts on the vehicles and stations from HaulSeeder.
 */
class FuelReceiptSeeder extends Seeder
{
    public function run(FuelReceiptLedger $ledger): void
    {
        $vehicles = Vehicle::query()->where('active', true)->get();
        $stations = FuelStation::query()->with('sites')->where('active', true)->get();
        $creator = User::query()->first();

        if ($vehicles->isEmpty() || $stations->isEmpty()) {
            return;
        }

        for ($i = 0; $i < 80; $i++) {
            $vehicle = $vehicles->random();
            $station = $stations->random();
            $issuedOn = today()->subDays(fake()->numberBetween(0, 10));
            $enteredAt = $issuedOn->copy()->setTime(fake()->numberBetween(8, 20), fake()->numberBetween(0, 59));
            $enteredAt = $enteredAt->isFuture() ? now() : $enteredAt;
            $owner = $vehicle->ownershipOn($issuedOn)?->party_id;

            if (! $owner) {
                continue;
            }

            $receipt = FuelReceipt::query()->forceCreate([
                'id' => (string) Str::uuid7($enteredAt),
                'ticket_no' => 'Y'.$issuedOn->format('Ym').fake()->unique()->numerify('#####'),
                'vehicle_id' => $vehicle->id,
                'party_id' => $owner,
                'fuel_station_id' => $station->id,
                'fuel_party_id' => $station->party_id,
                'site_id' => $station->sites->isEmpty() ? null : $station->sites->random()->id,
                'quantity' => fake()->randomElement([45, 60, 80, 100, 150]),
                'unit_price' => $station->unit_price,
                'issued_on' => $issuedOn->toDateString(),
                'km' => fake()->numberBetween(80000, 350000),
                'received_by' => $vehicle->driver_name,
                'shift' => fake()->boolean(70) ? Shift::Day : Shift::Night,
                'haul_ticket_count' => fake()->numberBetween(1, 5),
                'created_by' => $creator?->id,
                'created_at' => $enteredAt,
                'updated_at' => $enteredAt,
            ]);

            $ledger->sync($receipt);
        }
    }
}
