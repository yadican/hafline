<?php

namespace App\Repositories;

use App\Cache\FormOptionsCache;
use App\Models\VehicleClass;
use App\Support\Sort;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class VehicleClassRepository extends Repository
{
    public function __construct(VehicleClass $model)
    {
        parent::__construct($model);
    }

    /**
     * @param  array{q?: string, active?: string}  $filters
     */
    public function search(array $filters, ?Sort $sort = null, int $perPage = 20): LengthAwarePaginator
    {
        $query = $this->whereTerm($this->query()->withCount('vehicles'), $filters['q'] ?? null, ['name'])
            ->when(isset($filters['active']), fn (Builder $query) => $query->where('active', $filters['active'] === '1'));

        return $this->applySort($query, $sort)
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Active classes, plus the ones already in use so an edit form never loses a value.
     *
     * @param  string|list<string>|null  $include
     * @return array<string, string>
     */
    public function options(bool $activeOnly = false, string|array|null $include = null): array
    {
        $options = FormOptionsCache::remember(
            FormOptionsCache::VEHICLE_CLASSES,
            $activeOnly ? 'active' : 'all',
            fn (): array => $this->query()
                ->when($activeOnly, fn (Builder $query) => $query->where('active', true))
                ->orderBy('name')
                ->pluck('name', 'id')
                ->all(),
        );

        return FormOptionsCache::withIncludes(
            $options,
            $include,
            fn (array $ids): array => VehicleClass::withTrashed()->whereIn('id', $ids)->pluck('name', 'id')->all(),
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
            'vehicles' => 'vehicles_count',
        ];
    }
}
