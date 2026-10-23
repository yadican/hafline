<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\VehicleClassRequest;
use App\Http\Requests\Admin\VehicleClassSearchRequest;
use App\Models\VehicleClass;
use App\Services\VehicleClassService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class VehicleClassController extends AdminController
{
    protected ?string $permissionResource = 'vehicle-class';

    public function __construct(private VehicleClassService $classes) {}

    public function index(VehicleClassSearchRequest $request): View
    {
        $filters = $request->filters();

        return view('admin.vehicle-classes.index', [
            'title' => 'Araç sınıfları',
            'vehicleClasses' => $this->classes->paginate($filters, $request->sort()),
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        return view('admin.vehicle-classes.create', [
            'title' => 'Yeni araç sınıfı',
        ]);
    }

    public function store(VehicleClassRequest $request): RedirectResponse
    {
        return $this->complete(
            fn () => $this->classes->create($request->validated()),
            'admin.vehicle-classes.index',
            'Araç sınıfı oluşturuldu.',
        );
    }

    public function show(VehicleClass $vehicleClass): View
    {
        $vehicleClass->loadCount('vehicles');

        return view('admin.vehicle-classes.show', [
            'title' => 'Araç sınıfı detayı',
            'vehicleClass' => $vehicleClass,
        ]);
    }

    public function edit(VehicleClass $vehicleClass): View
    {
        return view('admin.vehicle-classes.edit', [
            'title' => 'Araç sınıfını düzenle',
            'vehicleClass' => $vehicleClass,
        ]);
    }

    public function update(VehicleClassRequest $request, VehicleClass $vehicleClass): RedirectResponse
    {
        return $this->complete(
            fn () => $this->classes->update($vehicleClass, $request->validated()),
            'admin.vehicle-classes.index',
            'Araç sınıfı güncellendi.',
        );
    }

    public function destroy(VehicleClass $vehicleClass): RedirectResponse
    {
        return $this->complete(
            fn () => $this->classes->delete($vehicleClass),
            'admin.vehicle-classes.index',
            'Araç sınıfı silindi.',
        );
    }
}
