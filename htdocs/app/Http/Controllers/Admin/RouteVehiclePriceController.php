<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\RouteVehiclePriceRequest;
use App\Models\HaulRoute;
use App\Models\RouteVehiclePrice;
use App\Services\HaulRouteService;
use Illuminate\Http\RedirectResponse;

class RouteVehiclePriceController extends AdminController
{
    protected ?string $permissionResource = 'haul-route';

    protected array $permissionMap = [
        'store' => 'edit-haul-route',
        'destroy' => 'edit-haul-route',
    ];

    public function __construct(private HaulRouteService $routes) {}

    public function store(RouteVehiclePriceRequest $request, HaulRoute $haulRoute): RedirectResponse
    {
        return $this->complete(
            fn () => $this->routes->setVehiclePrice($haulRoute, $request->vehicle(), (string) $request->validated('vehicle_price')),
            'admin.haul-routes.edit',
            'Araca özel fiyat kaydedildi.',
            [$haulRoute],
        );
    }

    public function destroy(HaulRoute $haulRoute, RouteVehiclePrice $vehiclePrice): RedirectResponse
    {
        return $this->complete(
            fn () => $this->routes->removeVehiclePrice($vehiclePrice),
            'admin.haul-routes.edit',
            'Araca özel fiyat kaldırıldı.',
            [$haulRoute],
        );
    }
}
