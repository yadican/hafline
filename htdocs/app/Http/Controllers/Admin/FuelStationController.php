<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\FuelStationRequest;
use App\Http\Requests\Admin\FuelStationSearchRequest;
use App\Models\FuelStation;
use App\Services\FuelStationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class FuelStationController extends AdminController
{
    protected ?string $permissionResource = 'fuel-station';

    public function __construct(private FuelStationService $stations) {}

    public function index(FuelStationSearchRequest $request): View
    {
        $filters = $request->filters();

        return view('admin.fuel-stations.index', [
            'title' => 'Akaryakıt istasyonları',
            'fuelStations' => $this->stations->paginate($filters, $request->sort()),
            'filters' => $filters,
            'sites' => $this->stations->siteOptions(activeOnly: false),
            'parties' => $this->stations->operatorOptions(),
        ]);
    }

    public function create(): View
    {
        return view('admin.fuel-stations.create', [
            'title' => 'Yeni akaryakıt istasyonu',
            'sites' => $this->stations->siteOptions(),
            'parties' => $this->stations->partyOptions(),
        ]);
    }

    public function store(FuelStationRequest $request): RedirectResponse
    {
        return $this->complete(
            fn () => $this->stations->create($request->validated()),
            'admin.fuel-stations.index',
            'Akaryakıt istasyonu oluşturuldu.',
        );
    }

    public function edit(FuelStation $fuelStation): View
    {
        return view('admin.fuel-stations.edit', [
            'title' => 'Akaryakıt istasyonunu düzenle',
            'fuelStation' => $fuelStation,
            'sites' => $this->stations->siteOptions($fuelStation),
            'parties' => $this->stations->partyOptions($fuelStation),
        ]);
    }

    public function update(FuelStationRequest $request, FuelStation $fuelStation): RedirectResponse
    {
        return $this->complete(
            fn () => $this->stations->update($fuelStation, $request->validated()),
            'admin.fuel-stations.index',
            'Akaryakıt istasyonu güncellendi.',
        );
    }

    public function destroy(FuelStation $fuelStation): RedirectResponse
    {
        return $this->complete(
            fn () => $this->stations->delete($fuelStation),
            'admin.fuel-stations.index',
            'Akaryakıt istasyonu silindi.',
        );
    }
}
