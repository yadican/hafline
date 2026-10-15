<?php

namespace Tests\Feature\Admin;

use App\Models\MachineWork;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class VehicleRateTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Vehicle $machine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
        $this->machine = Vehicle::factory()->create(['vehicle_class_id' => VehicleClass::factory()->hourly()]);
    }

    public function test_vehicle_class_can_be_marked_hourly(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.vehicle-classes.store'), ['name' => 'EKSKAVATÖR', 'hourly' => '1', 'active' => '1'])
            ->assertSessionHas('success');

        $this->assertTrue(VehicleClass::query()->where('name', 'EKSKAVATÖR')->sole()->hourly);
    }

    public function test_rate_section_is_shown_only_for_hourly_vehicles(): void
    {
        $this->actingAs($this->admin)->get(route('admin.vehicles.edit', $this->machine))->assertOk()->assertSee('Saat ücretleri');
        $this->actingAs($this->admin)->get(route('admin.vehicles.edit', Vehicle::factory()->create()))->assertOk()->assertDontSee('Saat ücretleri');
    }

    public function test_a_new_rate_closes_the_open_period(): void
    {
        $this->addRate('1200', '2026-01-01')->assertSessionHas('success');
        $this->addRate('1500', '2026-06-01')->assertRedirect(route('admin.vehicles.edit', $this->machine));

        $rates = $this->machine->rates()->get();

        $this->assertCount(2, $rates);
        $this->assertSame('1500.00', $rates[0]->hourly_rate);
        $this->assertNull($rates[0]->ended_on);
        $this->assertSame('2026-05-31', $rates[1]->ended_on->toDateString());
        $this->assertSame('1200.00', $this->machine->rateOn(now()->parse('2026-05-31'))->hourly_rate);
    }

    public function test_a_new_rate_must_start_after_the_current_one(): void
    {
        $this->addRate('1200', '2026-03-01');
        $this->addRate('1500', '2026-03-01')->assertSessionHas('error');

        $this->assertSame(1, $this->machine->rates()->count());
    }

    public function test_removing_the_latest_rate_reopens_the_previous_one_and_keeps_tickets(): void
    {
        $this->addRate('1200', '2026-01-01');
        $this->addRate('1500', '2026-06-01');
        $work = MachineWork::factory()->create(['vehicle_id' => $this->machine->id, 'hourly_rate' => 1500, 'worked_on' => '2026-06-10']);

        [$latest, $previous] = $this->machine->rates()->get()->all();

        $this->actingAs($this->admin)
            ->delete(route('admin.vehicles.rates.destroy', [$this->machine, $previous]))
            ->assertSessionHas('error');

        $this->actingAs($this->admin)
            ->delete(route('admin.vehicles.rates.destroy', [$this->machine, $latest]))
            ->assertSessionHas('success');

        $this->assertSame(1, $this->machine->rates()->count());
        $this->assertNull($previous->fresh()->ended_on);
        $this->assertSame('1500.00', $work->fresh()->hourly_rate);
    }

    private function addRate(string $rate, string $from): TestResponse
    {
        return $this->actingAs($this->admin)
            ->post(route('admin.vehicles.rates.store', $this->machine), ['hourly_rate' => $rate, 'rate_started_on' => $from]);
    }
}
