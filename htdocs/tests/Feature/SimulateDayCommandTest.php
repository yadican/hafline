<?php

namespace Tests\Feature;

use App\Models\DumpSiteClassPrice;
use App\Models\FuelStation;
use App\Models\Haul;
use App\Models\HaulRoute;
use App\Models\MachineWork;
use App\Models\Party;
use App\Models\RouteClassPrice;
use App\Models\Vehicle;
use App\Models\VehicleClass;
use Database\Seeders\LoadTest\DailyStage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SimulateDayCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        fake()->seed(7);

        $owner = Party::factory()->create();
        $route = HaulRoute::factory()->create();
        $class = VehicleClass::factory()->create(['name' => 'TIR']);
        RouteClassPrice::query()->create(['route_id' => $route->id, 'vehicle_class_id' => $class->id, 'price' => 2500]);
        DumpSiteClassPrice::query()->create(['dump_site_id' => $route->dump_site_id, 'vehicle_class_id' => $class->id, 'price' => 400]);

        $station = FuelStation::factory()->create(['unit_price' => 50]);
        DB::table('fuel_station_sites')->insert(['fuel_station_id' => $station->id, 'site_id' => $route->site_id]);

        foreach (range(1, 5) as $n) {
            Vehicle::factory()->ownedBy($owner, '2026-01-01')->create(['plate' => "34 TR 10{$n}", 'vehicle_class_id' => $class->id]);
        }

        $machine = Vehicle::factory()
            ->ownedBy($owner, '2026-01-01')
            ->create(['plate' => '34 MK 100', 'vehicle_class_id' => VehicleClass::factory()->hourly()]);
        $machine->rates()->create(['hourly_rate' => 1000, 'started_on' => '2026-01-01']);
    }

    public function test_a_simulated_day_is_entered_through_the_services_and_keeps_the_cari_consistent(): void
    {
        $this->artisan('simulate:day', ['date' => '2026-03-10', '--no-audit' => true])
            ->expectsOutputToContain('10.03.2026')
            ->assertSuccessful();

        $user = DailyStage::user();

        $this->assertGreaterThan(0, Haul::query()->where('created_by', $user->id)->count());
        $this->assertTrue(Haul::query()->get()->every(fn (Haul $haul) => $haul->hauled_at->isSameDay('2026-03-10') && (float) $haul->price === 2500.0));
        $this->assertSame(1, MachineWork::query()->where('created_by', $user->id)->count());
        $this->assertFalse($user->active, 'Simülasyon kullanıcısı panele giriş yapamaz.');

        $this->artisan('ledger:audit', ['--skip-reports' => true])->assertSuccessful();
    }

    public function test_the_same_day_is_not_simulated_twice_and_future_days_are_refused(): void
    {
        $this->artisan('simulate:day', ['date' => '2026-03-10', '--no-audit' => true])->assertSuccessful();
        $hauls = Haul::query()->count();

        $this->artisan('simulate:day', ['date' => '2026-03-10', '--no-audit' => true])
            ->expectsOutputToContain('zaten simüle edilmiş')
            ->assertSuccessful();
        $this->assertSame($hauls, Haul::query()->count());

        $this->artisan('simulate:day', ['date' => now()->addDays(2)->toDateString(), '--no-audit' => true])
            ->expectsOutputToContain('henüz gelmedi')
            ->assertSuccessful();
    }

    public function test_consecutive_days_continue_from_the_previous_day(): void
    {
        $this->artisan('simulate:day', ['date' => '2026-03-09', '--days' => 3, '--no-audit' => true])->assertSuccessful();

        $days = Haul::query()->get()->map(fn (Haul $haul) => $haul->hauled_at->toDateString())->unique()->sort()->values()->all();

        $this->assertSame(['2026-03-09', '2026-03-10', '2026-03-11'], $days);
        $this->artisan('ledger:audit', ['--skip-reports' => true])->assertSuccessful();
    }
}
