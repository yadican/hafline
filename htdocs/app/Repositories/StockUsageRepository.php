<?php

namespace App\Repositories;

use App\Enums\CashDirection;
use App\Models\CashCategory;
use App\Models\Site;
use App\Models\StockItem;
use App\Models\StockUsage;
use App\Models\Vehicle;
use App\Support\Sort;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class StockUsageRepository extends Repository
{
    public function __construct(StockUsage $model)
    {
        parent::__construct($model);
    }

    /**
     * @param  array{q?: string, stock_item_id?: string, site_id?: string, vehicle_id?: string, from?: string, to?: string}  $filters
     */
    public function search(array $filters, ?Sort $sort = null, int $perPage = 20): LengthAwarePaginator
    {
        $query = $this->query()
            ->with(['stockItem', 'site', 'vehicle', 'cashCategory.parent'])
            ->when($filters['stock_item_id'] ?? null, fn (Builder $query, string $id) => $query->where('stock_item_id', $id))
            ->when($filters['site_id'] ?? null, fn (Builder $query, string $id) => $query->where('site_id', $id))
            ->when($filters['vehicle_id'] ?? null, fn (Builder $query, string $id) => $query->where('vehicle_id', $id))
            ->when($filters['from'] ?? null, fn (Builder $query, string $from) => $query->whereDate('used_on', '>=', $from))
            ->when($filters['to'] ?? null, fn (Builder $query, string $to) => $query->whereDate('used_on', '<=', $to))
            ->when($filters['q'] ?? null, function (Builder $query, string $term): void {
                $like = $this->likePattern($term);
                $query->where(function (Builder $query) use ($like): void {
                    $query->whereLike('description', $like)
                        ->orWhereHas('stockItem', fn (Builder $item) => $item->whereLike('name', $like))
                        ->orWhereHas('site', fn (Builder $site) => $site->whereLike('name', $like))
                        ->orWhereHas('vehicle', fn (Builder $vehicle) => $vehicle->whereLike('plate_key', $like));
                });
            });

        return $this->applySort($query, $sort)
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Tracked active items for the usage form, plus any id already on the record.
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

    /**
     * @return array<string, string>
     */
    public function siteOptions(?string $include = null): array
    {
        return app(SiteRepository::class)->options(activeOnly: true, include: $include);
    }

    /**
     * @return array<string, string>
     */
    public function vehicleOptions(?string $include = null): array
    {
        $options = Vehicle::query()
            ->where('active', true)
            ->orderBy('plate_key')
            ->pluck('plate', 'id')
            ->all();

        if ($include && ! isset($options[$include])) {
            $plate = Vehicle::withTrashed()->whereKey($include)->value('plate');

            if ($plate !== null) {
                $options = [$include => $plate] + $options;
            }
        }

        return $options;
    }

    /**
     * @return array<string, string>
     */
    public function categoryOptions(?string $include = null): array
    {
        return app(CashCategoryRepository::class)->options(
            CashDirection::Expense,
            $include,
        );
    }

    protected function defaultSort(Builder $query): void
    {
        $query->orderByDesc('used_on')->orderByDesc('created_at');
    }

    protected function sortColumns(): array
    {
        return [
            'used_on' => 'used_on',
            'quantity' => 'quantity',
            'amount' => 'amount',
            'item' => fn (Builder $query, string $direction) => $query->orderBy(
                StockItem::withTrashed()->select('name')->whereColumn('stock_items.id', 'stock_usages.stock_item_id'),
                $direction,
            ),
            'site' => fn (Builder $query, string $direction) => $query->orderBy(
                Site::withTrashed()->select('name')->whereColumn('sites.id', 'stock_usages.site_id'),
                $direction,
            ),
            'vehicle' => fn (Builder $query, string $direction) => $query->orderBy(
                Vehicle::withTrashed()->select('plate_key')->whereColumn('vehicles.id', 'stock_usages.vehicle_id'),
                $direction,
            ),
            'category' => fn (Builder $query, string $direction) => $query->orderBy(
                CashCategory::withTrashed()->select('name')->whereColumn('cash_categories.id', 'stock_usages.cash_category_id'),
                $direction,
            ),
        ];
    }
}
