<?php

namespace Database\Seeders;

use App\Enums\PartyRole;
use App\Enums\Shift;
use App\Models\MachineWork;
use App\Models\Party;
use App\Models\Site;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleClass;
use App\Services\Ledger\MachineWorkLedger;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Sample machines with rate periods and a few days of work; expects the sites from HaulSeeder.
 */
class MachineWorkSeeder extends Seeder
{
    public function run(MachineWorkLedger $ledger): void
    {
        $this->callSilent(VehicleClassSeeder::class);
        $class = VehicleClass::query()->where('hourly', true)->orderBy('name')->firstOrFail();
        $sites = Site::query()->where('active', true)->get();

        if ($sites->isEmpty()) {
            return;
        }

        $owners = collect(['Ali Kepçe', 'Yusuf Ekskavatör'])
            ->map(fn (string $name) => Party::factory()->person()->create(['name' => $name]))
            ->push(Party::factory()->company()->create(['name' => 'GÜÇLÜ İŞ MAKİNALARI LTD. ŞTİ.']));

        // Kemal and Hasan are freelancers paid per ticket; Murat is salaried staff.
        $operators = collect(['Kemal Usta' => 250, 'Hasan Demir' => 300, 'Murat Kaya' => null])
            ->map(fn (?int $rate, string $name) => [
                'party' => Party::factory()->person()
                    ->withRole(...($rate === null ? [PartyRole::Employee, PartyRole::MachineOperator] : [PartyRole::MachineOperator]))
                    ->create(['name' => $name]),
                'rate' => $rate,
            ])
            ->values();

        $since = today()->subDays(60);

        $machines = collect(['34 MK 1001', '34 MK 2002', '34 MK 3003'])
            ->map(fn (string $plate, int $index) => Vehicle::factory()->ownedBy($owners[$index], $since->toDateString())->create([
                'plate' => $plate,
                'vehicle_class_id' => $class->id,
                'driver_name' => null,
            ]));

        foreach ($machines as $index => $machine) {
            $old = 1200 + $index * 150;

            $machine->rates()->create(['hourly_rate' => $old, 'started_on' => $since->toDateString(), 'ended_on' => today()->subDays(8)->toDateString()]);
            $machine->rates()->create(['hourly_rate' => $old + 250, 'started_on' => today()->subDays(7)->toDateString()]);
        }

        $creator = User::query()->first();

        for ($i = 0; $i < 30; $i++) {
            $machine = $machines->random();
            $operator = fake()->boolean(80) ? $operators->random() : null;
            $workedOn = today()->subDays(fake()->numberBetween(0, 12));
            $enteredAt = $workedOn->copy()->setTime(fake()->numberBetween(17, 22), fake()->numberBetween(0, 59));
            $enteredAt = $enteredAt->isFuture() ? now() : $enteredAt;

            $work = MachineWork::query()->forceCreate([
                'id' => (string) Str::uuid7($enteredAt),
                'ticket_no' => 'M'.$workedOn->format('Ym').fake()->unique()->numerify('#####'),
                'vehicle_id' => $machine->id,
                'party_id' => $machine->ownershipOn($workedOn)->party_id,
                'site_id' => $sites->random()->id,
                'operator_id' => $operator ? $operator['party']->id : null,
                'operator_rate' => $operator ? $operator['rate'] : null,
                'shift' => fake()->boolean(80) ? Shift::Day : Shift::Night,
                'hours' => fake()->randomElement([4, 6, 8, 8, 9.5, 10, 11]),
                'hourly_rate' => $machine->rateOn($workedOn)->hourly_rate,
                'worked_on' => $workedOn->toDateString(),
                'created_by' => $creator?->id,
                'created_at' => $enteredAt,
                'updated_at' => $enteredAt,
            ]);

            $ledger->sync($work);
        }
    }
}
