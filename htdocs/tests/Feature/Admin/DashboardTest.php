<?php

namespace Tests\Feature\Admin;

use App\Cache\DashboardCache;
use App\Models\CashCategory;
use App\Models\CashEntry;
use App\Models\Employee;
use App\Models\FuelReceipt;
use App\Models\Haul;
use App\Models\MachineWork;
use App\Models\Site;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\DashboardService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->yonetici()->create();
    }

    public function test_guest_is_redirected_from_the_dashboard(): void
    {
        $this->get(route('admin.dashboard'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_admin_sees_kpis_panels_and_shortcuts(): void
    {
        Site::factory()->create(['active' => true]);
        Site::factory()->create(['active' => false]);
        Vehicle::factory()->create(['active' => true]);
        Employee::factory()->create(['active' => true]);
        Haul::factory()->create(['hauled_at' => now()]);
        Haul::factory()->create(['hauled_at' => now()->subDay()]);
        FuelReceipt::factory()->create(['issued_on' => today()->toDateString()]);
        MachineWork::factory()->create(['worked_on' => today()->toDateString()]);

        CashEntry::query()->create([
            'cash_category_id' => CashCategory::factory()->create()->id,
            'debit' => 100,
            'credit' => 0,
            'occurred_on' => today()->toDateString(),
            'description' => 'Dashboard test',
            'created_by' => $this->admin->id,
        ]);

        $expected = [
            'Bugün taşıma' => Haul::query()->whereDate('hauled_at', today())->count(),
            'Bu ay taşıma' => Haul::query()
                ->where('hauled_at', '>=', today()->startOfMonth())
                ->where('hauled_at', '<=', today()->endOfDay())
                ->count(),
            'Bugün yakıt' => FuelReceipt::query()->whereDate('issued_on', today())->count(),
            'Aktif şantiye' => Site::query()->where('active', true)->count(),
            'Aktif araç' => Vehicle::query()->where('active', true)->count(),
            'Aktif personel' => Employee::query()->where('active', true)->count(),
        ];

        $this->actingAs($this->admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('stats', function (array $stats) use ($expected): bool {
                $byLabel = collect($stats)->mapWithKeys(
                    fn (array $stat) => [$stat['label'] => $stat['value']],
                );

                foreach ($expected as $label => $value) {
                    if (($byLabel[$label] ?? null) !== $value) {
                        return false;
                    }
                }

                return true;
            })
            ->assertSee('Bugünün panosu', false)
            ->assertSee('Bugünkü son taşımalar', false)
            ->assertSee('Bu ay kasa', false)
            ->assertSee(route('admin.hauls.index'), false)
            ->assertSee(route('admin.employees.index'), false)
            ->assertSee(route('admin.reports.cash-movements'), false);
    }

    public function test_dashboard_widgets_respect_permissions(): void
    {
        (new RolePermissionSeeder)->run();

        Haul::factory()->create(['hauled_at' => now()]);
        Site::factory()->create(['active' => true]);

        $user = User::factory()->create();
        $user->givePermissionTo(['view-dashboard', 'view-site']);

        $this->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('stats', function (array $stats): bool {
                $labels = collect($stats)->pluck('label');

                return $labels->contains('Aktif şantiye')
                    && ! $labels->contains('Bugün taşıma')
                    && ! $labels->contains('Bugün yakıt');
            })
            ->assertViewHas('recentHauls', null)
            ->assertViewHas('cashSummary', null)
            ->assertDontSee('Bugünkü son taşımalar', false)
            ->assertSee('Aktif şantiye', false);
    }

    public function test_dashboard_metrics_are_cached_per_key(): void
    {
        Cache::flush();

        Site::factory()->create(['active' => true]);
        Haul::factory()->create(['hauled_at' => now()]);

        $service = app(DashboardService::class);
        $first = $service->summary($this->admin);
        $second = $service->summary($this->admin);

        $this->assertSame(
            collect($first['stats'])->firstWhere('label', 'Aktif şantiye')['value'],
            collect($second['stats'])->firstWhere('label', 'Aktif şantiye')['value'],
        );

        $this->assertTrue(Cache::has(DashboardCache::PREFIX.'active-sites'));
        $this->assertTrue(Cache::has(DashboardCache::PREFIX.'hauls-day:'.today()->toDateString()));
        $this->assertTrue(Cache::has(DashboardCache::PREFIX.'recent-hauls:'.today()->toDateString()));
    }
}
