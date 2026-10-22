<?php

namespace App\Services;

use App\Cache\DashboardCache;
use App\Models\Employee;
use App\Models\FuelReceipt;
use App\Models\Haul;
use App\Models\MachineWork;
use App\Models\Site;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Reports\CashMovementReportService;
use Illuminate\Support\Carbon;

class DashboardService extends Service
{
    public function __construct(private CashMovementReportService $cashReports) {}

    /**
     * @return array{
     *     stats: list<array{
     *         label: string,
     *         value: int,
     *         icon: string,
     *         route: string,
     *         hint: string,
     *         tone: string,
     *         previous: ?int,
     *         previous_label: ?string
     *     }>,
     *     shortcuts: list<array{label: string, icon: string, route: string}>,
     *     recent_hauls: ?array{
     *         items: list<array{time: string, plate: string, summary: string}>,
     *         route: string
     *     },
     *     cash_summary: ?array{
     *         opening: float,
     *         total_giris: float,
     *         total_cikis: float,
     *         closing: float,
     *         period_label: string,
     *         route: string
     *     }
     * }
     */
    public function summary(User $user, ?Carbon $today = null): array
    {
        $today ??= today();

        return [
            'stats' => $this->stats($user, $today),
            'shortcuts' => $this->shortcuts($user),
            'recent_hauls' => $this->recentHauls($user, $today),
            'cash_summary' => $this->cashSummary($user, $today),
        ];
    }

    /**
     * @return list<array{
     *     label: string,
     *     value: int,
     *     icon: string,
     *     route: string,
     *     hint: string,
     *     tone: string,
     *     previous: ?int,
     *     previous_label: ?string
     * }>
     */
    private function stats(User $user, Carbon $today): array
    {
        $yesterday = $today->copy()->subDay();
        $monthKey = $today->format('Y-m');
        $stats = [];

        if ($user->can('view-haul')) {
            $todayHauls = $this->dayCount('hauls-day:'.$today->toDateString(), Haul::class, 'hauled_at', $today);
            $yesterdayHauls = $this->dayCount('hauls-day:'.$yesterday->toDateString(), Haul::class, 'hauled_at', $yesterday);

            $stats[] = $this->stat(
                label: 'Bugün taşıma',
                value: $todayHauls,
                icon: 'fa-solid fa-road',
                route: 'admin.hauls.index',
                hint: 'Taşıma fişleri',
                tone: 'haul',
                previous: $yesterdayHauls,
                previousLabel: 'dün',
            );

            $stats[] = $this->stat(
                label: 'Bu ay taşıma',
                value: $this->monthCount('hauls-month:'.$monthKey, Haul::class, 'hauled_at', $today),
                icon: 'fa-solid fa-chart-line',
                route: 'admin.hauls.index',
                hint: 'Taşıma fişleri',
                tone: 'haul-month',
            );
        }

        if ($user->can('view-fuel-receipt')) {
            $todayFuel = $this->dayCount('fuel-day:'.$today->toDateString(), FuelReceipt::class, 'issued_on', $today);
            $yesterdayFuel = $this->dayCount('fuel-day:'.$yesterday->toDateString(), FuelReceipt::class, 'issued_on', $yesterday);

            $stats[] = $this->stat(
                label: 'Bugün yakıt',
                value: $todayFuel,
                icon: 'fa-solid fa-gas-pump',
                route: 'admin.fuel-receipts.index',
                hint: 'Yakıt fişleri',
                tone: 'fuel',
                previous: $yesterdayFuel,
                previousLabel: 'dün',
            );

            $stats[] = $this->stat(
                label: 'Bu ay yakıt',
                value: $this->monthCount('fuel-month:'.$monthKey, FuelReceipt::class, 'issued_on', $today),
                icon: 'fa-solid fa-oil-can',
                route: 'admin.fuel-receipts.index',
                hint: 'Yakıt fişleri',
                tone: 'fuel-month',
            );
        }

        if ($user->can('view-machine-work')) {
            $todayMachine = $this->dayCount('machine-day:'.$today->toDateString(), MachineWork::class, 'worked_on', $today);
            $yesterdayMachine = $this->dayCount('machine-day:'.$yesterday->toDateString(), MachineWork::class, 'worked_on', $yesterday);

            $stats[] = $this->stat(
                label: 'Bugün makine',
                value: $todayMachine,
                icon: 'fa-solid fa-gears',
                route: 'admin.machine-works.index',
                hint: 'Makine çalışma',
                tone: 'machine',
                previous: $yesterdayMachine,
                previousLabel: 'dün',
            );
        }

        if ($user->can('view-site')) {
            $stats[] = $this->stat(
                label: 'Aktif şantiye',
                value: (int) DashboardCache::remember(
                    'active-sites',
                    fn (): int => Site::query()->where('active', true)->count(),
                ),
                icon: 'fa-solid fa-map-location-dot',
                route: 'admin.sites.index',
                hint: 'Şantiyeler',
                tone: 'site',
            );
        }

        if ($user->can('view-vehicle')) {
            $stats[] = $this->stat(
                label: 'Aktif araç',
                value: (int) DashboardCache::remember(
                    'active-vehicles',
                    fn (): int => Vehicle::query()->where('active', true)->count(),
                ),
                icon: 'fa-solid fa-truck',
                route: 'admin.vehicles.index',
                hint: 'Filo',
                tone: 'vehicle',
            );
        }

        if ($user->can('view-employee')) {
            $stats[] = $this->stat(
                label: 'Aktif personel',
                value: (int) DashboardCache::remember(
                    'active-employees',
                    fn (): int => Employee::query()->where('active', true)->count(),
                ),
                icon: 'fa-solid fa-user-tie',
                route: 'admin.employees.index',
                hint: 'Personel',
                tone: 'employee',
            );
        }

        return $stats;
    }

    /**
     * @return list<array{label: string, icon: string, route: string}>
     */
    private function shortcuts(User $user): array
    {
        $candidates = [
            ['label' => 'Taşıma fişleri', 'icon' => 'fa-solid fa-road', 'route' => 'admin.hauls.index', 'permission' => 'view-haul'],
            ['label' => 'Makine çalışma', 'icon' => 'fa-solid fa-gears', 'route' => 'admin.machine-works.index', 'permission' => 'view-machine-work'],
            ['label' => 'Yakıt fişleri', 'icon' => 'fa-solid fa-gas-pump', 'route' => 'admin.fuel-receipts.index', 'permission' => 'view-fuel-receipt'],
            ['label' => 'Cari hareketler', 'icon' => 'fa-solid fa-book', 'route' => 'admin.cari-entries.index', 'permission' => 'view-cari-entry'],
            ['label' => 'Personel', 'icon' => 'fa-solid fa-user-tie', 'route' => 'admin.employees.index', 'permission' => 'view-employee'],
            ['label' => 'Şantiyeler', 'icon' => 'fa-solid fa-map-location-dot', 'route' => 'admin.sites.index', 'permission' => 'view-site'],
            ['label' => 'Araçlar', 'icon' => 'fa-solid fa-truck', 'route' => 'admin.vehicles.index', 'permission' => 'view-vehicle'],
            ['label' => 'Kasa hareketleri', 'icon' => 'fa-solid fa-wallet', 'route' => 'admin.reports.cash-movements', 'permission' => 'view-report-cash-movements'],
            ['label' => 'Kullanıcılar', 'icon' => 'fa-solid fa-users', 'route' => 'admin.users.index', 'permission' => 'view-users'],
            ['label' => 'Roller', 'icon' => 'fa-solid fa-user-shield', 'route' => 'admin.roles.index', 'permission' => 'view-role'],
        ];

        $shortcuts = [];

        foreach ($candidates as $candidate) {
            if (! $user->can($candidate['permission'])) {
                continue;
            }

            $shortcuts[] = [
                'label' => $candidate['label'],
                'icon' => $candidate['icon'],
                'route' => $candidate['route'],
            ];
        }

        return $shortcuts;
    }

    /**
     * @return ?array{
     *     items: list<array{time: string, plate: string, summary: string}>,
     *     route: string
     * }
     */
    private function recentHauls(User $user, Carbon $today): ?array
    {
        if (! $user->can('view-haul')) {
            return null;
        }

        /** @var list<array{time: string, plate: string, summary: string}> $items */
        $items = DashboardCache::remember(
            'recent-hauls:'.$today->toDateString(),
            function () use ($today): array {
                return Haul::query()
                    ->with(['vehicle:id,plate', 'site:id,name', 'dumpSite:id,name'])
                    ->whereDate('hauled_at', $today)
                    ->orderByDesc('hauled_at')
                    ->limit(8)
                    ->get()
                    ->map(fn (Haul $haul): array => [
                        'time' => $haul->hauled_at?->format('H:i') ?? '—',
                        'plate' => $haul->vehicle?->plate ?? '—',
                        'summary' => ($haul->site?->name ?? '—').' → '.($haul->dumpSite?->name ?? '—'),
                    ])
                    ->all();
            },
        );

        return [
            'items' => $items,
            'route' => 'admin.hauls.index',
        ];
    }

    /**
     * @return ?array{
     *     opening: float,
     *     total_giris: float,
     *     total_cikis: float,
     *     closing: float,
     *     period_label: string,
     *     route: string
     * }
     */
    private function cashSummary(User $user, Carbon $today): ?array
    {
        if (! $user->can('view-report-cash-movements')) {
            return null;
        }

        /** @var array{opening: float, total_giris: float, total_cikis: float, closing: float, period_label: string} $summary */
        $summary = DashboardCache::remember(
            'cash-month:'.$today->format('Y-m'),
            function () use ($today): array {
                $report = $this->cashReports->report([
                    'period' => 'month',
                    'year' => (int) $today->year,
                    'month' => (int) $today->month,
                ]);

                return [
                    'opening' => $report['opening'],
                    'total_giris' => $report['total_giris'],
                    'total_cikis' => $report['total_cikis'],
                    'closing' => $report['closing'],
                    'period_label' => $report['period_label'],
                ];
            },
        );

        return [
            ...$summary,
            'route' => 'admin.reports.cash-movements',
        ];
    }

    /**
     * @param  class-string  $model
     */
    private function dayCount(string $cacheKey, string $model, string $column, Carbon $day): int
    {
        return (int) DashboardCache::remember(
            $cacheKey,
            fn (): int => $model::query()->whereDate($column, $day)->count(),
        );
    }

    /**
     * @param  class-string  $model
     */
    private function monthCount(string $cacheKey, string $model, string $column, Carbon $day): int
    {
        return (int) DashboardCache::remember(
            $cacheKey,
            function () use ($model, $column, $day): int {
                $from = $day->copy()->startOfMonth();
                $to = $day->copy()->endOfDay();

                return $model::query()
                    ->where($column, '>=', $from)
                    ->where($column, '<=', $to)
                    ->count();
            },
        );
    }

    /**
     * @return array{
     *     label: string,
     *     value: int,
     *     icon: string,
     *     route: string,
     *     hint: string,
     *     tone: string,
     *     previous: ?int,
     *     previous_label: ?string
     * }
     */
    private function stat(
        string $label,
        int $value,
        string $icon,
        string $route,
        string $hint,
        string $tone,
        ?int $previous = null,
        ?string $previousLabel = null,
    ): array {
        return [
            'label' => $label,
            'value' => $value,
            'icon' => $icon,
            'route' => $route,
            'hint' => $hint,
            'tone' => $tone,
            'previous' => $previous,
            'previous_label' => $previousLabel,
        ];
    }
}
