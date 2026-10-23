<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\HaulRouteRequest;
use App\Http\Requests\Admin\HaulRouteSearchRequest;
use App\Models\HaulRoute;
use App\Services\HaulRouteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class HaulRouteController extends AdminController
{
    protected ?string $permissionResource = 'haul-route';

    public function __construct(private HaulRouteService $routes) {}

    public function index(HaulRouteSearchRequest $request): View
    {
        $filters = $request->filters();

        return view('admin.haul-routes.index', [
            'title' => 'Güzergahlar',
            'haulRoutes' => $this->routes->paginate($filters, $request->sort()),
            'filters' => $filters,
            'options' => $this->routes->searchOptions(),
        ]);
    }

    public function create(): View
    {
        return view('admin.haul-routes.create', [
            'title' => 'Yeni güzergah',
            'options' => $this->routes->formOptions(),
        ]);
    }

    public function store(HaulRouteRequest $request): RedirectResponse
    {
        return $this->complete(
            fn () => $this->routes->create($request->validated()),
            'admin.haul-routes.index',
            'Güzergah oluşturuldu.',
        );
    }

    public function show(HaulRoute $haulRoute): View
    {
        $haulRoute->load([
            'site',
            'dumpSite',
            'classPrices.vehicleClass',
            'vehiclePrices.vehicle.vehicleClass',
        ]);

        return view('admin.haul-routes.show', [
            'title' => 'Güzergah detayı',
            'haulRoute' => $haulRoute,
        ]);
    }

    public function edit(HaulRoute $haulRoute): View
    {
        $haulRoute->load(['site', 'dumpSite', 'classPrices', 'vehiclePrices.vehicle.vehicleClass']);

        return view('admin.haul-routes.edit', [
            'title' => 'Güzergahı düzenle',
            'haulRoute' => $haulRoute,
            'options' => $this->routes->formOptions($haulRoute),
        ]);
    }

    public function update(HaulRouteRequest $request, HaulRoute $haulRoute): RedirectResponse
    {
        return $this->complete(
            fn () => $this->routes->update($haulRoute, $request->validated()),
            'admin.haul-routes.index',
            'Güzergah güncellendi.',
        );
    }

    public function destroy(HaulRoute $haulRoute): RedirectResponse
    {
        return $this->complete(
            fn () => $this->routes->delete($haulRoute),
            'admin.haul-routes.index',
            'Güzergah silindi.',
        );
    }
}
