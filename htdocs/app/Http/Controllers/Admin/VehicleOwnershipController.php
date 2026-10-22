<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\VehicleOwnershipRequest;
use App\Models\Vehicle;
use App\Models\VehicleOwnership;
use App\Services\VehicleOwnershipService;
use Illuminate\Http\RedirectResponse;

class VehicleOwnershipController extends AdminController
{
    protected ?string $permissionResource = 'vehicle';

    protected array $permissionMap = [
        'store' => 'edit-vehicle',
        'destroy' => 'edit-vehicle',
    ];

    public function __construct(private VehicleOwnershipService $ownerships) {}

    public function store(VehicleOwnershipRequest $request, Vehicle $vehicle): RedirectResponse
    {
        return $this->complete(
            fn () => $this->ownerships->transfer($vehicle, $request->party(), $request->startedOn()),
            'admin.vehicles.edit',
            'Araç devredildi.',
            [$vehicle],
        );
    }

    public function destroy(Vehicle $vehicle, VehicleOwnership $ownership): RedirectResponse
    {
        return $this->complete(
            fn () => $this->ownerships->undo($vehicle, $ownership),
            'admin.vehicles.edit',
            'Son devir geri alındı.',
            [$vehicle],
        );
    }
}
