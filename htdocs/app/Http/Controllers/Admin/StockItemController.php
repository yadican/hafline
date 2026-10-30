<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\StockItemRequest;
use App\Http\Requests\Admin\StockItemSearchRequest;
use App\Models\StockItem;
use App\Repositories\StockRepository;
use App\Services\StockItemService;
use App\Services\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class StockItemController extends AdminController
{
    protected ?string $permissionResource = 'stock-item';

    public function __construct(private StockItemService $stockItems) {}

    public function index(StockItemSearchRequest $request): View
    {
        $filters = $request->filters();

        return view('admin.stock-items.index', [
            'title' => 'Stok kalemleri',
            'stockItems' => $this->stockItems->paginate($filters, $request->sort()),
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        return view('admin.stock-items.create', [
            'title' => 'Yeni stok kalemi',
            'cashCategories' => $this->stockItems->cashCategoryOptions(),
        ]);
    }

    public function store(StockItemRequest $request): RedirectResponse
    {
        return $this->complete(
            fn () => $this->stockItems->create($request->validated()),
            'admin.stock-items.index',
            'Stok kalemi oluşturuldu.',
        );
    }

    public function show(StockItem $stockItem, StockService $stock, StockRepository $movements): View
    {
        $stockItem->load('cashCategory.parent');
        $tracks = $stockItem->tracks_stock;

        return view('admin.stock-items.show', [
            'title' => 'Stok kalemi detayı',
            'stockItem' => $stockItem,
            'stock' => $tracks ? $stock->stock($stockItem) : null,
            'movements' => $tracks ? $movements->movements($stockItem)->reverse()->values() : collect(),
        ]);
    }

    public function edit(StockItem $stockItem): View
    {
        return view('admin.stock-items.edit', [
            'title' => 'Stok kalemini düzenle',
            'stockItem' => $stockItem,
            'cashCategories' => $this->stockItems->cashCategoryOptions($stockItem),
        ]);
    }

    public function update(StockItemRequest $request, StockItem $stockItem): RedirectResponse
    {
        return $this->complete(
            fn () => $this->stockItems->update($stockItem, $request->validated()),
            'admin.stock-items.index',
            'Stok kalemi güncellendi.',
        );
    }

    public function destroy(StockItem $stockItem): RedirectResponse
    {
        return $this->complete(
            fn () => $this->stockItems->delete($stockItem),
            'admin.stock-items.index',
            'Stok kalemi silindi.',
        );
    }
}
