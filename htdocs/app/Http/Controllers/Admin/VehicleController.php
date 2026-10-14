<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\VehicleRequest;
use App\Http\Requests\Admin\VehicleSearchRequest;
use App\Models\Vehicle;
use App\Services\VehicleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class VehicleController extends AdminController
{
    public function __construct(private VehicleService $vehicles) {}

    public function index(VehicleSearchRequest $request): View
    {
        $filters = $request->filters();

        return view('admin.vehicles.index', [
            'title' => 'Araçlar',
            'vehicles' => $this->vehicles->paginate($filters, $request->sort()),
            'filters' => $filters,
            'options' => $this->vehicles->searchOptions(),
        ]);
    }

    public function create(): View
    {
        return view('admin.vehicles.create', [
            'title' => 'Yeni araç',
            'options' => $this->vehicles->formOptions(),
        ]);
    }

    public function store(VehicleRequest $request): RedirectResponse
    {
        return $this->complete(
            fn () => $this->vehicles->create($request->validated()),
            'admin.vehicles.index',
            'Araç oluşturuldu.',
        );
    }

    public function edit(Vehicle $vehicle): View
    {
        return view('admin.vehicles.edit', [
            'title' => 'Aracı düzenle',
            'vehicle' => $vehicle,
            'options' => $this->vehicles->formOptions($vehicle),
        ]);
    }

    public function update(VehicleRequest $request, Vehicle $vehicle): RedirectResponse
    {
        return $this->complete(
            fn () => $this->vehicles->update($vehicle, $request->validated()),
            'admin.vehicles.index',
            'Araç güncellendi.',
        );
    }

    public function destroy(Vehicle $vehicle): RedirectResponse
    {
        return $this->complete(
            fn () => $this->vehicles->delete($vehicle),
            'admin.vehicles.index',
            'Araç silindi.',
        );
    }
}
