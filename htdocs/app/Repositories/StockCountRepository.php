<?php

namespace App\Repositories;

use App\Models\StockCount;
use App\Models\StockItem;
use App\Support\Sort;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class StockCountRepository extends Repository
{
    public function __construct(StockCount $model)
    {
        parent::__construct($model);
    }

    /**
     * @param  array{q?: string, stock_item_id?: string, from?: string, to?: string}  $filters
     */
    public function search(array $filters, ?Sort $sort = null, int $perPage = 20): LengthAwarePaginator
    {
        $query = $this->query()
            ->with(['stockItem'])
            ->when($filters['stock_item_id'] ?? null, fn (Builder $query, string $id) => $query->where('stock_item_id', $id))
            ->when($filters['from'] ?? null, fn (Builder $query, string $from) => $query->whereDate('counted_on', '>=', $from))
            ->when($filters['to'] ?? null, fn (Builder $query, string $to) => $query->whereDate('counted_on', '<=', $to))
            ->when($filters['q'] ?? null, function (Builder $query, string $term): void {
                $like = $this->likePattern($term);
                $query->where(function (Builder $query) use ($like): void {
                    $query->whereLike('description', $like)
                        ->orWhereHas('stockItem', fn (Builder $item) => $item->whereLike('name', $like));
                });
            });

        return $this->applySort($query, $sort)
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Tracked active items for the count form, plus any id already on the record.
     *
     * @return array<string, string>
     */
    public function stockItemOptions(?string $include = null): array
    {
        $options = StockItem::query()
            ->where('active', true)
            ->where('tracks_stock', true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();

        if ($include && ! isset($options[$include])) {
            $name = StockItem::withTrashed()->whereKey($include)->value('name');

            if ($name !== null) {
                $options = [$include => $name] + $options;
            }
        }

        return $options;
    }

    protected function defaultSort(Builder $query): void
    {
        $query->orderByDesc('counted_on')->orderByDesc('created_at');
    }

    protected function sortColumns(): array
    {
        return [
            'counted_on' => 'counted_on',
            'quantity' => 'quantity',
            'difference' => 'difference',
            'item' => fn (Builder $query, string $direction) => $query->orderBy(
                StockItem::withTrashed()->select('name')->whereColumn('stock_items.id', 'stock_counts.stock_item_id'),
                $direction,
            ),
        ];
    }
}
