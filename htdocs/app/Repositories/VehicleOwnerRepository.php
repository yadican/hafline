<?php

namespace App\Repositories;

use App\Models\VehicleOwner;
use App\Support\Sort;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class VehicleOwnerRepository extends Repository
{
    public function __construct(VehicleOwner $model)
    {
        parent::__construct($model);
    }

    /**
     * Every word must match somewhere, so "ali yılmaz" finds the owner across both name columns.
     *
     * @param  array{q?: string}  $filters
     */
    public function search(array $filters, ?Sort $sort = null, int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->query()->withCount('vehicles');

        foreach (preg_split('/\s+/u', trim($filters['q'] ?? ''), flags: PREG_SPLIT_NO_EMPTY) as $word) {
            $this->whereTerm($query, $word, ['first_name', 'last_name', 'phone', 'mobile']);
        }

        return $this->applySort($query, $sort)
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @return array<string, string>
     */
    public function options(): array
    {
        return $this->query()
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get()
            ->mapWithKeys(fn (VehicleOwner $owner) => [$owner->id => $owner->full_name])
            ->all();
    }

    protected function defaultSort(Builder $query): void
    {
        $query->orderBy('first_name')->orderBy('last_name');
    }

    protected function sortColumns(): array
    {
        return [
            'name' => fn (Builder $query, string $direction) => $query
                ->orderBy('first_name', $direction)
                ->orderBy('last_name', $direction),
            'vehicles' => 'vehicles_count',
        ];
    }
}
