<?php

namespace Tests\Feature\Api;

use App\Models\FieldHaulSession;
use App\Models\FuelReceipt;
use App\Models\Haul;
use App\Models\HaulRoute;
use App\Models\MachineWork;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class DashboardApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Vehicle $vehicle;

    private HaulRoute $route;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-10-15 12:00:00', config('app.timezone')));

        $this->user = User::factory()->apiOperator()->create();
        $this->vehicle = Vehicle::factory()->create();
        $this->route = HaulRoute::factory()->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_dashboard_requires_token(): void
    {
        $this->getJson('/api/v1/dashboard')->assertUnauthorized();
    }

    public function test_counts_only_include_own_tickets_within_day_and_month_bounds(): void
    {
        $otherUser = User::factory()->create();

        $this->haul('2026-10-15 08:00:00');
        $this->haul('2026-10-14 20:00:00');
        $this->haul('2026-10-01 00:00:00');
        $this->haul('2026-09-30 23:59:00');
        $this->haul('2026-10-15 09:00:00', $otherUser);

        MachineWork::factory()->create(['created_by' => $this->user->id, 'worked_on' => '2026-10-15']);
        MachineWork::factory()->create(['created_by' => $this->user->id, 'worked_on' => '2026-10-14']);
        MachineWork::factory()->create(['created_by' => $this->user->id, 'worked_on' => '2026-09-30']);

        FuelReceipt::factory()->count(2)->create(['created_by' => $this->user->id, 'issued_on' => '2026-10-15']);
        FuelReceipt::factory()->create(['created_by' => $otherUser->id, 'issued_on' => '2026-10-15']);

        $this->api()
            ->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('counts.hauls', ['today' => 1, 'yesterday' => 1, 'month' => 3])
            ->assertJsonPath('counts.machine_works', ['today' => 1, 'yesterday' => 1, 'month' => 2])
            ->assertJsonPath('counts.fuel_receipts', ['today' => 2, 'yesterday' => 0, 'month' => 2]);
    }

    public function test_recent_lists_todays_own_tickets_newest_first_up_to_ten(): void
    {
        foreach (range(0, 8) as $minute) {
            $this->haul(createdAt: sprintf('2026-10-15 07:%02d:00', $minute));
        }

        $this->haul(createdAt: '2026-10-15 09:00:00');
        $this->haul(createdAt: '2026-10-14 23:00:00');
        $this->haul(createdAt: '2026-10-15 11:30:00', creator: User::factory()->create());

        FuelReceipt::factory()->create([
            'created_by' => $this->user->id,
            'vehicle_id' => $this->vehicle->id,
            'created_at' => '2026-10-15 10:00:00',
        ]);

        MachineWork::factory()->create([
            'created_by' => $this->user->id,
            'created_at' => '2026-10-15 11:00:00',
        ]);

        $recent = $this->api()
            ->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonCount(10, 'recent')
            ->json('recent');

        $this->assertSame(['machine_work', 'fuel_receipt', 'haul'], array_column(array_slice($recent, 0, 3), 'type'));
        $this->assertSame(
            ['11:00', '10:00', '09:00', '07:08', '07:07', '07:06', '07:05', '07:04', '07:03', '07:02'],
            array_column($recent, 'time'),
        );
        $this->assertSame($this->vehicle->plate, $recent[1]['plate']);
    }

    public function test_field_session_is_null_without_open_session_and_has_no_cash_data(): void
    {
        $response = $this->api()
            ->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('field_session', null)
            ->assertJsonPath('user.id', $this->user->id);

        $this->assertSame(['user', 'field_session', 'counts', 'recent'], array_keys($response->json()));
    }

    public function test_field_session_returns_open_session(): void
    {
        $session = FieldHaulSession::query()->create([
            'user_id' => $this->user->id,
            'site_id' => $this->route->site_id,
            'dump_site_id' => $this->route->dump_site_id,
            'shift' => 'day',
            'locked_at' => now(),
        ]);

        $this->api()
            ->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('field_session.id', $session->id)
            ->assertJsonPath('field_session.site_id', $this->route->site_id)
            ->assertJsonPath('field_session.shift', 'day');
    }

    private function api(): static
    {
        return $this->withHeader('Authorization', 'Bearer '.JWTAuth::fromUser($this->user));
    }

    private function haul(?string $hauledAt = null, ?User $creator = null, ?string $createdAt = null): Haul
    {
        return Haul::factory()->create([
            'created_by' => ($creator ?? $this->user)->id,
            'vehicle_id' => $this->vehicle->id,
            'route_id' => $this->route->id,
            'hauled_at' => $hauledAt ?? $createdAt,
            'created_at' => $createdAt ?? $hauledAt,
        ]);
    }
}
