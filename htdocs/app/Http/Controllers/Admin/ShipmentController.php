<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\ShipmentRequest;
use App\Http\Requests\Admin\ShipmentSearchRequest;
use App\Models\Shipment;
use App\Services\ShipmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ShipmentController extends AdminController
{
    protected ?string $permissionResource = 'shipment';

    public function __construct(private ShipmentService $shipments) {}

    public function index(ShipmentSearchRequest $request): View
    {
        $filters = $request->filters();

        return view('admin.shipments.index', [
            'title' => 'Seferler',
            'shipments' => $this->shipments->paginate($filters, $request->sort()),
            'totals' => $this->shipments->totals($filters),
            'filters' => $filters,
            'options' => $this->shipments->searchOptions(),
        ]);
    }

    public function create(): View
    {
        return view('admin.shipments.create', [
            'title' => 'Yeni sefer',
            'options' => $this->shipments->formOptions(),
        ]);
    }

    public function store(ShipmentRequest $request): RedirectResponse
    {
        return $this->complete(
            fn () => $this->shipments->create($request->validated(), $request->user()?->id),
            'admin.shipments.index',
            'Sefer kaydedildi.',
        );
    }

    public function show(Shipment $shipment): View
    {
        return view('admin.shipments.show', [
            'title' => 'Sefer detayı',
            'shipment' => $shipment->load(['vehicle', 'party', 'deliveryAddress', 'customer', 'driver', 'commissionParty', 'creator']),
        ]);
    }

    public function edit(Shipment $shipment): View
    {
        return view('admin.shipments.edit', [
            'title' => 'Seferi düzenle',
            'shipment' => $shipment->load(['vehicle', 'deliveryAddress']),
            'options' => $this->shipments->formOptions($shipment),
        ]);
    }

    public function update(ShipmentRequest $request, Shipment $shipment): RedirectResponse
    {
        return $this->complete(
            fn () => $this->shipments->update($shipment, $request->validated()),
            'admin.shipments.index',
            'Sefer güncellendi.',
        );
    }

    public function destroy(Shipment $shipment): RedirectResponse
    {
        return $this->complete(
            fn () => $this->shipments->delete($shipment),
            'admin.shipments.index',
            'Sefer silindi.',
        );
    }
}
