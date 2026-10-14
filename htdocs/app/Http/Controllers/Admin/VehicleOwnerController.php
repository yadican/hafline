<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\VehicleOwnerRequest;
use App\Http\Requests\Admin\VehicleOwnerSearchRequest;
use App\Models\VehicleOwner;
use App\Services\VehicleOwnerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class VehicleOwnerController extends AdminController
{
    public function __construct(private VehicleOwnerService $owners) {}

    public function index(VehicleOwnerSearchRequest $request): View
    {
        $filters = $request->filters();

        return view('admin.vehicle-owners.index', [
            'title' => 'Araç sahipleri',
            'vehicleOwners' => $this->owners->paginate($filters, $request->sort()),
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        return view('admin.vehicle-owners.create', [
            'title' => 'Yeni araç sahibi',
        ]);
    }

    public function store(VehicleOwnerRequest $request): RedirectResponse
    {
        return $this->complete(
            fn () => $this->owners->create($request->validated()),
            'admin.vehicle-owners.index',
            'Araç sahibi oluşturuldu.',
        );
    }

    public function edit(VehicleOwner $vehicleOwner): View
    {
        return view('admin.vehicle-owners.edit', [
            'title' => 'Araç sahibini düzenle',
            'vehicleOwner' => $vehicleOwner,
        ]);
    }

    public function update(VehicleOwnerRequest $request, VehicleOwner $vehicleOwner): RedirectResponse
    {
        return $this->complete(
            fn () => $this->owners->update($vehicleOwner, $request->validated()),
            'admin.vehicle-owners.index',
            'Araç sahibi güncellendi.',
        );
    }

    public function destroy(VehicleOwner $vehicleOwner): RedirectResponse
    {
        return $this->complete(
            fn () => $this->owners->delete($vehicleOwner),
            'admin.vehicle-owners.index',
            'Araç sahibi silindi.',
        );
    }
}
