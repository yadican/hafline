<?php

namespace App\Repositories;

use App\Cache\FormOptionsCache;
use App\Models\StockItem;
use App\Support\Sort;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class StockItemRepository extends Repository
{
    public function __construct(StockItem $model)
    {
        parent::__construct($model);
    }

    /**
     * @param  array{q?: string, unit?: string, tracks_stock?: string, active?: string}  $filters
     */
    public function search(array $filters, ?Sort $sort = null, int $perPage = 20): LengthAwarePaginator
    {
        $query = $this->whereTerm($this->query(), $filters['q'] ?? null, ['name'])
            ->with('cashCategory.parent')
            ->when($filters['unit'] ?? null, fn (Builder $query, string $unit) => $query->where('unit', $unit))
            ->when(isset($filters['tracks_stock']), fn (Builder $query) => $query->where('tracks_stock', $filters['tracks_stock'] === '1'))
            ->when(isset($filters['active']), fn (Builder $query) => $query->where('active', $filters['active'] === '1'));

        return $this->applySort($query, $sort)
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Active stock items for selects, plus any ids already chosen on a record.
     *
     * @param  string|list<string>|null  $include
     * @return array<string, string>
     */
    public function options(string|array|null $include = null): array
    {
        $options = FormOptionsCache::remember(
            FormOptionsCache::STOCK_ITEMS,
            'active',
            fn (): array => $this->query()
                ->where('active', true)
                ->orderBy('name')
                ->pluck('name', 'id')
                ->all(),
        );

        return FormOptionsCache::withIncludes(
            $options,
            $include,
            fn (array $ids): array => StockItem::withTrashed()->whereIn('id', $ids)->pluck('name', 'id')->all(),
        );
    }

    protected function defaultSort(Builder $query): void
    {
        $query->orderBy('name');
    }

    protected function sortColumns(): array
    {
        return [
            'name' => 'name',
            'unit' => 'unit',
            'sales_price' => 'sales_price',
        ];
    }
}
