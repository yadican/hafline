<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\FuelStationRequest;
use App\Http\Requests\Admin\FuelStationSearchRequest;
use App\Models\FuelStation;
use App\Repositories\FuelTankRepository;
use App\Services\FuelStationService;
use App\Services\FuelTankService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FuelStationController extends AdminController
{
    protected ?string $permissionResource = 'fuel-station';

    public function __construct(private FuelStationService $stations) {}

    public function index(FuelStationSearchRequest $request): View
    {
        $filters = $request->filters();

        return view('admin.fuel-stations.index', [
            'title' => 'Yakıt yerleri',
            'fuelStations' => $this->stations->paginate($filters, $request->sort()),
            'filters' => $filters,
            'sites' => $this->stations->siteOptions(activeOnly: false),
            'parties' => $this->stations->operatorOptions(),
        ]);
    }

    public function create(): View
    {
        return view('admin.fuel-stations.create', [
            'title' => 'Yeni yakıt kaynağı',
            'sites' => $this->stations->siteOptions(),
            'parties' => $this->stations->partyOptions(),
        ]);
    }

    public function store(FuelStationRequest $request): RedirectResponse
    {
        return $this->complete(
            fn () => $this->stations->create($request->validated()),
            'admin.fuel-stations.index',
            'Yakıt kaynağı oluşturuldu.',
        );
    }

    public function show(Request $request, FuelStation $fuelStation, FuelTankService $tanks, FuelTankRepository $movements): View
    {
        $fuelStation->load(['party', 'sites']);
        $showTank = $fuelStation->isTank() && $request->user()?->can('view-fuel-tank');

        return view('admin.fuel-stations.show', [
            'title' => 'Yakıt kaynağı detayı',
            'fuelStation' => $fuelStation,
            'tank' => $showTank ? [
                'stock' => $tanks->stock($fuelStation),
                'fills' => $movements->fills($fuelStation),
                'counts' => $movements->counts($fuelStation),
                'suppliers' => $tanks->supplierOptions(),
            ] : null,
        ]);
    }

    public function edit(FuelStation $fuelStation): View
    {
        return view('admin.fuel-stations.edit', [
            'title' => 'Yakıt kaynağını düzenle',
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
            'Yakıt kaynağı güncellendi.',
        );
    }

    public function destroy(FuelStation $fuelStation): RedirectResponse
    {
        return $this->complete(
            fn () => $this->stations->delete($fuelStation),
            'admin.fuel-stations.index',
            'Yakıt kaynağı silindi.',
        );
    }
}
