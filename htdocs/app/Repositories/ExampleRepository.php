<?php

namespace App\Repositories;

use App\Models\Example;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ExampleRepository extends Repository
{
    public function __construct(Example $model)
    {
        parent::__construct($model);
    }

    /**
     * @param  array{q?: string, active?: string, shift?: string}  $filters
     */
    public function search(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->query()
            ->when($filters['q'] ?? null, function (Builder $query, string $term): void {
                $like = '%'.addcslashes($term, '%_\\').'%';

                $query->where(function (Builder $query) use ($like): void {
                    $query->whereLike('title', $like)
                        ->orWhereLike('email', $like)
                        ->orWhereLike('phone', $like)
                        ->orWhereLike('reference', $like);
                });
            })
            ->when(isset($filters['active']), fn (Builder $query) => $query->where('active', $filters['active'] === '1'))
            ->when($filters['shift'] ?? null, fn (Builder $query, string $shift) => $query->where('shift', $shift))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }
}
