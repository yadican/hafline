<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\FuelReceiptRequest;
use App\Http\Requests\Admin\FuelReceiptSearchRequest;
use App\Models\FuelReceipt;
use App\Services\FuelReceiptService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class FuelReceiptController extends AdminController
{
    protected ?string $permissionResource = 'fuel-receipt';

    public function __construct(private FuelReceiptService $receipts) {}

    public function index(FuelReceiptSearchRequest $request): View
    {
        $filters = $request->filters();

        return view('admin.fuel-receipts.index', [
            'title' => 'Yakıt fişleri',
            'receipts' => $this->receipts->paginate($filters, $request->sort()),
            'totals' => $this->receipts->totals($filters),
            'filters' => $filters,
            'options' => $this->receipts->searchOptions(),
        ]);
    }

    public function create(): View
    {
        return view('admin.fuel-receipts.create', [
            'title' => 'Yeni yakıt fişi',
            'options' => $this->receipts->formOptions(),
        ]);
    }

    public function store(FuelReceiptRequest $request): RedirectResponse
    {
        return $this->complete(
            fn () => $this->receipts->create($request->validated(), $request->user()?->id),
            'admin.fuel-receipts.index',
            'Yakıt fişi kaydedildi.',
        );
    }

    public function edit(FuelReceipt $fuelReceipt): View
    {
        return view('admin.fuel-receipts.edit', [
            'title' => 'Yakıt fişini düzenle',
            'receipt' => $fuelReceipt->load('vehicle'),
            'options' => $this->receipts->formOptions($fuelReceipt),
        ]);
    }

    public function update(FuelReceiptRequest $request, FuelReceipt $fuelReceipt): RedirectResponse
    {
        return $this->complete(
            fn () => $this->receipts->update($fuelReceipt, $request->validated()),
            'admin.fuel-receipts.index',
            'Yakıt fişi güncellendi.',
        );
    }

    public function destroy(FuelReceipt $fuelReceipt): RedirectResponse
    {
        return $this->complete(
            fn () => $this->receipts->delete($fuelReceipt),
            'admin.fuel-receipts.index',
            'Yakıt fişi silindi.',
        );
    }
}
