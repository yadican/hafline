<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\FuelTankCountRequest;
use App\Http\Requests\Admin\FuelTankFillRequest;
use App\Models\FuelStation;
use App\Models\FuelTankCount;
use App\Models\FuelTankFill;
use App\Services\FuelTankService;
use Illuminate\Http\RedirectResponse;

class FuelTankController extends AdminController
{
    protected ?string $permissionResource = 'fuel-tank';

    protected array $permissionMap = [
        'storeFill' => 'create-fuel-tank',
        'destroyFill' => 'delete-fuel-tank',
        'storeCount' => 'create-fuel-tank',
        'destroyCount' => 'delete-fuel-tank',
    ];

    public function __construct(private FuelTankService $tanks) {}

    public function storeFill(FuelTankFillRequest $request, FuelStation $fuelStation): RedirectResponse
    {
        return $this->complete(
            fn () => $this->tanks->addFill($fuelStation, $request->validated(), $request->user()?->id),
            'admin.fuel-stations.show',
            'Yakıt dolumu kaydedildi.',
            [$fuelStation],
        );
    }

    public function destroyFill(FuelStation $fuelStation, FuelTankFill $fill): RedirectResponse
    {
        return $this->complete(
            fn () => $this->tanks->deleteFill($fill),
            'admin.fuel-stations.show',
            'Yakıt dolumu silindi.',
            [$fuelStation],
        );
    }

    public function storeCount(FuelTankCountRequest $request, FuelStation $fuelStation): RedirectResponse
    {
        return $this->complete(
            fn () => $this->tanks->addCount($fuelStation, $request->validated(), $request->user()?->id),
            'admin.fuel-stations.show',
            'Yakıt sayımı kaydedildi.',
            [$fuelStation],
        );
    }

    public function destroyCount(FuelStation $fuelStation, FuelTankCount $count): RedirectResponse
    {
        return $this->complete(
            fn () => $this->tanks->deleteCount($count),
            'admin.fuel-stations.show',
            'Yakıt sayımı silindi.',
            [$fuelStation],
        );
    }
}
