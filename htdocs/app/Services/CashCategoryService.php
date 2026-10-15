<?php

namespace App\Services;

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
    public function paginate(array $filters = [], ?Sort $sort = null, int $perPage = 15): LengthAwarePaginator
    {
        return $this->cashCategories->search($filters, $sort, $perPage);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): CashCategory
    {
        /** @var CashCategory */
        return $this->transaction(fn () => $this->cashCategories->create($attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(CashCategory $cashCategory, array $attributes): CashCategory
    {
        /** @var CashCategory */
        return $this->transaction(fn () => $this->cashCategories->update($cashCategory, $attributes));
    }

    public function delete(CashCategory $cashCategory): void
    {
        $this->transaction(fn () => $this->cashCategories->delete($cashCategory));
    }
}
