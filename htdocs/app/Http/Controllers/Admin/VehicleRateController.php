<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\VehicleRateRequest;
use App\Models\Vehicle;
use App\Models\VehicleRate;
use App\Services\VehicleRateService;
use Illuminate\Http\RedirectResponse;

class VehicleRateController extends AdminController
{
    protected ?string $permissionResource = 'vehicle';

    protected array $permissionMap = [
        'store' => 'edit-vehicle',
        'destroy' => 'edit-vehicle',
    ];

    public function __construct(private VehicleRateService $rates) {}

    public function store(VehicleRateRequest $request, Vehicle $vehicle): RedirectResponse
    {
        return $this->complete(
            fn () => $this->rates->add($vehicle, $request->hourlyRate(), $request->startedOn()),
            'admin.vehicles.edit',
            'Saat ücreti kaydedildi.',
            [$vehicle],
        );
    }

    public function destroy(Vehicle $vehicle, VehicleRate $rate): RedirectResponse
    {
        return $this->complete(
            fn () => $this->rates->undo($vehicle, $rate),
            'admin.vehicles.edit',
            'Son ücret dönemi silindi.',
            [$vehicle],
        );
    }
}
