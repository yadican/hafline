<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\DeliveryAddressRequest;
use App\Http\Requests\Admin\TransportDefinitionSearchRequest;
use App\Models\DeliveryAddress;
use App\Repositories\MaterialRepository;
use App\Services\DeliveryAddressService;
use App\Services\TransportPriceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DeliveryAddressController extends AdminController
{
    protected ?string $permissionResource = 'delivery-address';

    public function __construct(
        private DeliveryAddressService $addresses,
        private TransportPriceService $prices,
    ) {}

    public function index(TransportDefinitionSearchRequest $request): View
    {
        $filters = $request->filters();

        return view('admin.delivery-addresses.index', [
            'title' => 'Sevk yerleri',
            'addresses' => $this->addresses->paginate($filters, $request->sort()),
            'filters' => $filters,
            'customers' => $this->addresses->customerOptions(),
        ]);
    }

    public function create(): View
    {
        return view('admin.delivery-addresses.create', [
            'title' => 'Yeni sevk yeri',
            'parties' => $this->addresses->partyOptions(),
        ]);
    }

    public function store(DeliveryAddressRequest $request): RedirectResponse
    {
        return $this->complete(
            fn () => $this->addresses->create($request->validated()),
            'admin.delivery-addresses.index',
            'Sevk yeri oluşturuldu.',
        );
    }

    public function edit(DeliveryAddress $deliveryAddress, MaterialRepository $materials): View
    {
        return view('admin.delivery-addresses.edit', [
            'title' => 'Sevk yerini düzenle',
            'address' => $deliveryAddress,
            'parties' => $this->addresses->partyOptions($deliveryAddress),
            'prices' => $this->prices->forAddress($deliveryAddress),
            'materials' => $materials->options(),
        ]);
    }

    public function update(DeliveryAddressRequest $request, DeliveryAddress $deliveryAddress): RedirectResponse
    {
        return $this->complete(
            fn () => $this->addresses->update($deliveryAddress, $request->validated()),
            'admin.delivery-addresses.index',
            'Sevk yeri güncellendi.',
        );
    }

    public function destroy(DeliveryAddress $deliveryAddress): RedirectResponse
    {
        return $this->complete(
            fn () => $this->addresses->delete($deliveryAddress),
            'admin.delivery-addresses.index',
            'Sevk yeri silindi.',
        );
    }
}
