<?php

namespace App\Services;

use App\Models\FuelReceipt;
use App\Models\Haul;
use App\Models\Site;
use App\Models\Vehicle;
use Illuminate\Support\Carbon;

class DashboardService extends Service
{
    /**
     * @return array{
     *     stats: list<array{label: string, value: int, icon: string, route: string, hint: string}>,
     *     shortcuts: list<array{label: string, icon: string, route: string}>
     * }
     */
    public function summary(?Carbon $today = null): array
    {
        $today ??= today();

        return [
            'stats' => $this->stats($today),
            'shortcuts' => $this->shortcuts(),
        ];
    }

    /**
     * @return list<array{label: string, value: int, icon: string, route: string, hint: string}>
     */
    private function stats(Carbon $today): array
    {
        return [
            [
                'label' => 'Aktif şantiye',
                'value' => Site::query()->where('active', true)->count(),
                'icon' => 'fa-solid fa-map-location-dot',
                'route' => 'admin.sites.index',
                'hint' => 'Şantiyeler',
            ],
            [
                'label' => 'Aktif araç',
                'value' => Vehicle::query()->where('active', true)->count(),
                'icon' => 'fa-solid fa-truck',
                'route' => 'admin.vehicles.index',
                'hint' => 'Filo',
            ],
            [
                'label' => 'Bugün taşıma',
                'value' => Haul::query()->whereDate('hauled_at', $today)->count(),
                'icon' => 'fa-solid fa-road',
                'route' => 'admin.hauls.index',
                'hint' => 'Taşıma fişleri',
            ],
            [
                'label' => 'Bugün yakıt',
                'value' => FuelReceipt::query()->whereDate('issued_on', $today)->count(),
                'icon' => 'fa-solid fa-gas-pump',
                'route' => 'admin.fuel-receipts.index',
                'hint' => 'Yakıt fişleri',
            ],
        ];
    }

    /**
     * @return list<array{label: string, icon: string, route: string}>
     */
    private function shortcuts(): array
    {
        return [
            ['label' => 'Taşıma fişleri', 'icon' => 'fa-solid fa-road', 'route' => 'admin.hauls.index'],
            ['label' => 'Makine çalışma', 'icon' => 'fa-solid fa-gears', 'route' => 'admin.machine-works.index'],
            ['label' => 'Yakıt fişleri', 'icon' => 'fa-solid fa-gas-pump', 'route' => 'admin.fuel-receipts.index'],
            ['label' => 'Cari hareketler', 'icon' => 'fa-solid fa-book', 'route' => 'admin.cari-entries.index'],
            ['label' => 'Kişiler', 'icon' => 'fa-solid fa-user', 'route' => 'admin.persons.index'],
            ['label' => 'Şirketler', 'icon' => 'fa-solid fa-building', 'route' => 'admin.companies.index'],
            ['label' => 'Araçlar', 'icon' => 'fa-solid fa-truck', 'route' => 'admin.vehicles.index'],
            ['label' => 'Araç sınıfları', 'icon' => 'fa-solid fa-layer-group', 'route' => 'admin.vehicle-classes.index'],
            ['label' => 'Şantiyeler', 'icon' => 'fa-solid fa-map-location-dot', 'route' => 'admin.sites.index'],
            ['label' => 'Döküm yerleri', 'icon' => 'fa-solid fa-mountain', 'route' => 'admin.dump-sites.index'],
            ['label' => 'Güzergahlar', 'icon' => 'fa-solid fa-route', 'route' => 'admin.haul-routes.index'],
            ['label' => 'Akaryakıt istasyonları', 'icon' => 'fa-solid fa-oil-well', 'route' => 'admin.fuel-stations.index'],
            ['label' => 'Kasa kategorileri', 'icon' => 'fa-solid fa-tags', 'route' => 'admin.cash-categories.index'],
            ['label' => 'Bankalar', 'icon' => 'fa-solid fa-building-columns', 'route' => 'admin.banks.index'],
        ];
    }
}
