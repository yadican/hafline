<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\StockCountRequest;
use App\Http\Requests\Admin\StockCountSearchRequest;
use App\Models\StockCount;
use App\Services\StockCountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class StockCountController extends AdminController
{
    protected ?string $permissionResource = 'stock-count';

    public function __construct(private StockCountService $counts) {}

    public function index(StockCountSearchRequest $request): View
    {
        $filters = $request->filters();

        return view('admin.stock-counts.index', [
            'title' => 'Stok sayımları',
            'counts' => $this->counts->paginate($filters, $request->sort()),
            'filters' => $filters,
            'options' => $this->counts->formOptions(),
        ]);
    }

    public function create(): View
    {
        return view('admin.stock-counts.create', [
            'title' => 'Yeni stok sayımı',
            ...$this->counts->formOptions(),
        ]);
    }

    public function store(StockCountRequest $request): RedirectResponse
    {
        return $this->complete(
            fn () => $this->counts->create($request->validated()),
            'admin.stock-counts.index',
            'Stok sayımı kaydedildi.',
        );
    }

    public function show(StockCount $stockCount): View
    {
        $stockCount->load(['stockItem']);

        return view('admin.stock-counts.show', [
            'title' => 'Stok sayımı detayı',
            'count' => $stockCount,
        ]);
    }

    public function edit(StockCount $stockCount): View
    {
        return view('admin.stock-counts.edit', [
            'title' => 'Stok sayımını düzenle',
            'count' => $stockCount,
            ...$this->counts->formOptions($stockCount),
        ]);
    }

    public function update(StockCountRequest $request, StockCount $stockCount): RedirectResponse
    {
        return $this->complete(
            fn () => $this->counts->update($stockCount, $request->validated()),
            'admin.stock-counts.index',
            'Stok sayımı güncellendi.',
        );
    }

    public function destroy(StockCount $stockCount): RedirectResponse
    {
        return $this->complete(
            fn () => $this->counts->delete($stockCount),
            'admin.stock-counts.index',
            'Stok sayımı silindi.',
        );
    }
}
