<?php

namespace App\Services;

use App\Cache\CashCategoryCache;
use App\Cache\FormOptionsCache;
use App\Exceptions\OperationRejectedException;
use App\Models\CashCategory;
use App\Repositories\CashCategoryRepository;
use App\Support\Sort;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CashCategoryService extends Service
{
    public function __construct(private CashCategoryRepository $cashCategories) {}

    /**
     * @param  array{q?: string, direction?: string, active?: string}  $filters
     */
    public function paginate(array $filters = [], ?Sort $sort = null, int $perPage = CashCategoryCache::PER_PAGE): LengthAwarePaginator
    {
        return CashCategoryCache::rememberFirstPage(
            $filters,
            $sort,
            fn () => $this->cashCategories->search($filters, $sort, $perPage),
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): CashCategory
    {
        return $this->transaction(function () use ($attributes): CashCategory {
            /** @var CashCategory $cashCategory */
            $cashCategory = $this->cashCategories->create($attributes);

            $this->flushCaches();

            return $cashCategory;
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(CashCategory $cashCategory, array $attributes): CashCategory
    {
        return $this->transaction(function () use ($cashCategory, $attributes): CashCategory {
            /** @var CashCategory $updated */
            $updated = $this->cashCategories->update($cashCategory, $attributes);

            $this->flushCaches();

            return $updated;
        });
    }

    /**
     * @return array<string, string>
     */
    public function groupOptions(?CashCategory $except = null): array
    {
        return $this->cashCategories->groupOptions($except?->id);
    }

    public function delete(CashCategory $cashCategory): void
    {
        if ($cashCategory->children()->exists()) {
            throw new OperationRejectedException('Bu grubun altında kategori var; silinemez. Önce alt kategorileri başka gruba taşıyın veya silin.');
        }

        $this->transaction(function () use ($cashCategory): void {
            $this->cashCategories->delete($cashCategory);
            $this->flushCaches();
        });
    }

    private function flushCaches(): void
    {
        CashCategoryCache::flush();
        FormOptionsCache::flush(FormOptionsCache::CASH_CATEGORIES);
    }
}
