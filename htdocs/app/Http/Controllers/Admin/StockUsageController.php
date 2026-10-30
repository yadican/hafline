<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\StockUsageRequest;
use App\Http\Requests\Admin\StockUsageSearchRequest;
use App\Models\StockUsage;
use App\Services\StockUsageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class StockUsageController extends AdminController
{
    protected ?string $permissionResource = 'stock-usage';

    public function __construct(private StockUsageService $usages) {}

    public function index(StockUsageSearchRequest $request): View
    {
        $filters = $request->filters();

        return view('admin.stock-usages.index', [
            'title' => 'Stok kullanımları',
            'usages' => $this->usages->paginate($filters, $request->sort()),
            'filters' => $filters,
            'options' => $this->usages->formOptions(),
        ]);
    }

    public function create(): View
    {
        return view('admin.stock-usages.create', [
            'title' => 'Yeni stok kullanımı',
            ...$this->usages->formOptions(),
        ]);
    }

    public function store(StockUsageRequest $request): RedirectResponse
    {
        return $this->complete(
            fn () => $this->usages->create($request->validated()),
            'admin.stock-usages.index',
            'Stok kullanımı kaydedildi.',
        );
    }

    public function show(StockUsage $stockUsage): View
    {
        $stockUsage->load(['stockItem', 'site', 'vehicle', 'cashCategory.parent']);

        return view('admin.stock-usages.show', [
            'title' => 'Stok kullanımı detayı',
            'usage' => $stockUsage,
        ]);
    }

    public function edit(StockUsage $stockUsage): View
    {
        return view('admin.stock-usages.edit', [
            'title' => 'Stok kullanımını düzenle',
            'usage' => $stockUsage,
            ...$this->usages->formOptions($stockUsage),
        ]);
    }

    public function update(StockUsageRequest $request, StockUsage $stockUsage): RedirectResponse
    {
        return $this->complete(
            fn () => $this->usages->update($stockUsage, $request->validated()),
            'admin.stock-usages.index',
            'Stok kullanımı güncellendi.',
        );
    }

    public function destroy(StockUsage $stockUsage): RedirectResponse
    {
        return $this->complete(
            fn () => $this->usages->delete($stockUsage),
            'admin.stock-usages.index',
            'Stok kullanımı silindi.',
        );
    }
}
