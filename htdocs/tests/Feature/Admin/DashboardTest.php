<?php

namespace Tests\Feature\Admin;

use App\Models\FuelReceipt;
use App\Models\Haul;
use App\Models\Site;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
    }

    public function test_guest_is_redirected_from_the_dashboard(): void
    {
        $this->get(route('admin.dashboard'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_admin_sees_live_counts_and_module_shortcuts(): void
    {
        Site::factory()->create(['active' => true]);
        Site::factory()->create(['active' => false]);
        Vehicle::factory()->create(['active' => true]);
        Haul::factory()->create(['hauled_at' => now()]);
        Haul::factory()->create(['hauled_at' => now()->subDay()]);
        FuelReceipt::factory()->create(['issued_on' => today()->toDateString()]);

        $expected = [
            'Aktif şantiye' => Site::query()->where('active', true)->count(),
            'Aktif araç' => Vehicle::query()->where('active', true)->count(),
            'Bugün taşıma' => Haul::query()->whereDate('hauled_at', today())->count(),
            'Bugün yakıt' => FuelReceipt::query()->whereDate('issued_on', today())->count(),
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
            ->assertSee('Operasyon özeti', false)
            ->assertSee(route('admin.hauls.index'), false)
            ->assertSee(route('admin.cari-entries.index'), false)
            ->assertSee(route('admin.sites.index'), false);
    }
}
