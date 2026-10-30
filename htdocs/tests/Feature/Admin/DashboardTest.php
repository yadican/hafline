<?php

namespace Tests\Feature\Admin;

use App\Cache\DashboardCache;
use App\Enums\AccountType;
use App\Enums\InstrumentDirection;
use App\Enums\InstrumentStatus;
use App\Enums\PartyRole;
use App\Enums\PaymentMethod;
use App\Models\Bank;
use App\Models\CashCategory;
use App\Models\CashEntry;
use App\Models\Employee;
use App\Models\FinancialInstrument;
use App\Models\FuelReceipt;
use App\Models\Haul;
use App\Models\MachineWork;
use App\Models\Party;
use App\Models\Site;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\DashboardService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
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

    public function test_day_counts_include_both_ends_of_the_day_only(): void
    {
        $this->travelTo(today()->setTime(12, 0));

        Haul::factory()->create(['hauled_at' => today()->subDay()->setTime(23, 59, 59)]);
        Haul::factory()->create(['hauled_at' => today()->setTime(0, 0)]);
        Haul::factory()->create(['hauled_at' => today()->setTime(23, 59, 59)]);
        Haul::factory()->create(['hauled_at' => today()->addDay()->setTime(0, 0)]);

        $this->actingAs($this->admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('stats', fn (array $stats): bool => collect($stats)->firstWhere('label', 'Bugün taşıma')['value'] === 2
                && collect($stats)->firstWhere('label', 'Bugün taşıma')['previous'] === 1)
            ->assertViewHas('recentHauls', fn (array $recent): bool => count($recent['items']) === 2);
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
            ->assertViewHas('financeReminders', null)
            ->assertViewHas('invoiceReminders', null)
            ->assertDontSee('Bugünkü son taşımalar', false)
            ->assertDontSee('Finans hatırlatıcıları', false)
            ->assertDontSee('Alacaklar', false)
            ->assertSee('Aktif şantiye', false);
    }

    public function test_finance_reminders_group_open_cheques_and_notes_by_due_window(): void
    {
        $this->travelTo('2026-10-30 12:00:00');
        $party = Party::factory()->company()->withRole(PartyRole::Customer)->create(['name' => 'Vade Müşterisi']);

        $overdue = $this->instrument($party, '2026-10-27', InstrumentDirection::Received, 1500);
        $this->instrument($party, '2026-10-29', InstrumentDirection::Received, 900, InstrumentStatus::Collected);
        $this->instrument($party, '2026-10-30', InstrumentDirection::Given, 2000);
        $this->instrument($party, '2026-11-05', InstrumentDirection::Received, 300, InstrumentStatus::InCollection);
        $this->instrument($party, '2026-11-06', InstrumentDirection::Received, 400);
        $this->instrument($party, '2026-11-29', InstrumentDirection::Given, 700);
        $this->instrument($party, '2026-11-30', InstrumentDirection::Given, 800);

        $this->actingAs($this->admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('financeReminders', function (array $reminders) use ($overdue): bool {
                $tabs = collect($reminders['tabs'])->keyBy('key');

                $this->assertSame('overdue', $reminders['active']);
                $this->assertSame(
                    ['overdue' => [1, 1500.0, 0.0], 'week' => [2, 300.0, 2000.0], 'month' => [2, 400.0, 700.0]],
                    $tabs->map(fn (array $tab): array => [$tab['count'], $tab['received'], $tab['given']])->all(),
                );
                $this->assertSame($overdue->id, $tabs['overdue']['items'][0]['id']);
                $this->assertSame('3 gün geçti', $tabs['overdue']['items'][0]['when']);
                $this->assertSame(['Bugün', '6 gün kaldı'], array_column($tabs['week']['items'], 'when'));
                $this->assertSame(['due_from' => '2026-11-06', 'due_to' => '2026-11-29'], $tabs['month']['filters']);

                return true;
            })
            ->assertSee('Finans hatırlatıcıları', false)
            ->assertSee('Vade Müşterisi', false)
            ->assertSee(route('admin.financial-instruments.show', $overdue), false);
    }

    public function test_collecting_a_cheque_drops_it_from_the_cached_finance_reminders(): void
    {
        $this->travelTo('2026-10-30 12:00:00');
        $party = Party::factory()->company()->withRole(PartyRole::Customer)->create();
        $cheque = $this->instrument($party, '2026-10-27', InstrumentDirection::Received, 1500);
        $overdueCount = fn (array $reminders): int => collect($reminders['tabs'])->firstWhere('key', 'overdue')['count'];

        $this->actingAs($this->admin)
            ->get(route('admin.dashboard'))
            ->assertViewHas('financeReminders', fn (array $reminders): bool => $overdueCount($reminders) === 1);

        $this->assertTrue(Cache::has(DashboardCache::PREFIX.DashboardCache::financeRemindersKey(today())));

        $this->actingAs($this->admin)
            ->post(route('admin.financial-instruments.movements.store', $cheque), [
                'type' => 'collect',
                'occurred_on' => '2026-10-30',
                'bank_id' => Bank::factory()->create(['type' => AccountType::Bank])->id,
            ])
            ->assertSessionHas('success');

        $this->actingAs($this->admin)
            ->get(route('admin.dashboard'))
            ->assertViewHas('financeReminders', fn (array $reminders): bool => $overdueCount($reminders) === 0);
    }

    public function test_finance_reminders_are_shown_to_users_who_can_view_cheques_and_notes(): void
    {
        (new RolePermissionSeeder)->run();

        $user = User::factory()->create();
        $user->givePermissionTo(['view-dashboard', 'view-financial-instrument']);

        $this->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('financeReminders', fn (?array $reminders): bool => $reminders !== null)
            ->assertSee('Finans hatırlatıcıları', false);
    }

    public function test_finance_reminders_open_on_the_upcoming_week_when_nothing_is_due(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('financeReminders', fn (array $reminders): bool => $reminders['active'] === 'week'
                && collect($reminders['tabs'])->sum('count') === 0)
            ->assertSee('Vadesi geçmiş açık çek / senet yok.', false);
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

    private function instrument(Party $party, string $dueOn, InstrumentDirection $direction, float $amount, ?InstrumentStatus $status = null): FinancialInstrument
    {
        return FinancialInstrument::query()->create([
            'payment_method' => PaymentMethod::Cheque,
            'direction' => $direction,
            'status' => $status ?? $direction->initialStatus(),
            'amount' => $amount,
            'issued_on' => '2026-10-01',
            'due_on' => $dueOn,
            'party_id' => $party->id,
            'source_type' => 'company_transaction',
            'source_id' => (string) Str::uuid(),
        ]);
    }
}
