<?php

namespace App\Services;

use App\Cache\FormOptionsCache;
use App\Cache\StockItemCache;
use App\Enums\CashDirection;
use App\Models\StockItem;
use App\Repositories\CashCategoryRepository;
use App\Repositories\StockItemRepository;
use App\Support\Sort;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class StockItemService extends Service
{
    public function __construct(
        private StockItemRepository $stockItems,
        private CashCategoryRepository $cashCategories,
    ) {}

    /**
     * @param  array{q?: string, unit?: string, tracks_stock?: string, active?: string}  $filters
     */
    public function paginate(array $filters = [], ?Sort $sort = null, int $perPage = StockItemCache::PER_PAGE): LengthAwarePaginator
    {
        return StockItemCache::rememberFirstPage(
            $filters,
            $sort,
            fn () => $this->stockItems->search($filters, $sort, $perPage),
        );
    }

    /**
     * Expense (and shared) categories for the default category picker.
     *
     * @return array<string, string>
     */
    public function cashCategoryOptions(?StockItem $stockItem = null): array
    {
        return $this->cashCategories->options(CashDirection::Expense, $stockItem?->cash_category_id);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): StockItem
    {
        return $this->transaction(function () use ($attributes): StockItem {
            /** @var StockItem $stockItem */
            $stockItem = $this->stockItems->create($attributes);

            $this->flushCaches();

            return $stockItem;
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(StockItem $stockItem, array $attributes): StockItem
    {
        return $this->transaction(function () use ($stockItem, $attributes): StockItem {
            /** @var StockItem $updated */
            $updated = $this->stockItems->update($stockItem, $attributes);

            $this->flushCaches();

            return $updated;
        });
    }

    public function delete(StockItem $stockItem): void
    {
        $this->transaction(function () use ($stockItem): void {
            $this->stockItems->delete($stockItem);
            $this->flushCaches();
        });
    }

    private function flushCaches(): void
    {
        StockItemCache::flush();
        FormOptionsCache::flush(FormOptionsCache::STOCK_ITEMS);
    }
}
