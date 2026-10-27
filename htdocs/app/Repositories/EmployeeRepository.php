<?php

namespace App\Repositories;

use App\Cache\FormOptionsCache;
use App\Enums\PartyType;
use App\Models\Employee;
use App\Models\Party;
use App\Support\Sort;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class EmployeeRepository extends Repository
{
    public function __construct(Employee $model)
    {
        parent::__construct($model);
    }

    /**
     * @param  array{q?: string, active?: string}  $filters
     */
    public function search(array $filters, ?Sort $sort = null, int $perPage = 20): LengthAwarePaginator
    {
        $query = $this->query()
            ->with(['party.person', 'periods'])
            ->when($filters['q'] ?? null, function (Builder $query, string $term): void {
                $like = $this->likePattern($term);
                $query->whereHas('party', fn (Builder $query) => $query
                    ->whereLike('name', $like)
                    ->orWhereLike('phone', $like)
                    ->orWhereLike('mobile', $like));
            })
            ->when(isset($filters['active']), fn (Builder $query) => $query->where('active', $filters['active'] === '1'));

        return $this->applySort($query, $sort)
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Active employees for select lists, optionally including a soft-deleted/inactive one already chosen.
     *
     * @return array<string, string>
     */
    public function options(?string $include = null, bool $withPeriodOnly = false): array
    {
        $options = FormOptionsCache::remember(
            FormOptionsCache::EMPLOYEES,
            $withPeriodOnly ? 'with_period' : 'all',
            function () use ($withPeriodOnly): array {
                return $this->query()
                    ->with('party')
                    ->where('active', true)
                    ->when($withPeriodOnly, fn (Builder $query) => $query->whereHas('periods', fn (Builder $query) => $query->whereNull('ended_on')))
                    ->get()
                    ->sortBy(fn (Employee $employee) => mb_strtolower($employee->name))
                    ->mapWithKeys(fn (Employee $employee) => [$employee->id => $employee->name])
                    ->all();
            },
        );

        return FormOptionsCache::withIncludes(
            $options,
            $include,
            function (array $ids): array {
                return Employee::withTrashed()
                    ->with('party')
                    ->whereIn('id', $ids)
                    ->get()
                    ->mapWithKeys(fn (Employee $employee) => [$employee->id => $employee->name])
                    ->all();
            },
        );
    }

    /**
     * Employees whose employment period covers the given month start.
     *
     * @return Collection<int, Employee>
     */
    public function coveringPeriod(string $period): Collection
    {
        $periodEnd = date('Y-m-t', strtotime($period));

        return $this->query()
            ->with(['party.person', 'periods'])
            ->where('active', true)
            ->whereHas('periods', function (Builder $query) use ($period, $periodEnd): void {
                $query
                    ->where(fn (Builder $query) => $query
                        ->whereNull('started_on')
                        ->orWhere('started_on', '<=', $periodEnd))
                    ->where(fn (Builder $query) => $query
                        ->whereNull('ended_on')
                        ->orWhere('ended_on', '>=', $period));
            })
            ->get()
            ->sortBy(fn (Employee $employee) => mb_strtolower($employee->name))
            ->values();
    }

    /**
     * @return Collection<int, Employee>
     */
    public function active(): Collection
    {
        return $this->query()
            ->with('party')
            ->where('active', true)
            ->get();
    }

    /**
     * Active persons without an employee card, plus an optional include for edit forms.
     *
     * @return array<string, string>
     */
    public function availablePartyOptions(?string $include = null): array
    {
        return Party::query()
            ->where('type', PartyType::Person)
            ->where(fn (Builder $query) => $query
                ->where(fn (Builder $query) => $query
                    ->where('active', true)
                    ->whereDoesntHave('employee'))
                ->when($include, fn (Builder $query, string $id) => $query->orWhere('id', $id)))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    protected function defaultSort(Builder $query): void
    {
        $query->orderBy(
            Party::query()
                ->select('name')
                ->whereColumn('parties.id', 'employees.party_id')
                ->limit(1)
        );
    }

    protected function sortColumns(): array
    {
        return [
            'name' => fn (Builder $query, string $direction) => $query->orderBy(
                Party::query()
                    ->select('name')
                    ->whereColumn('parties.id', 'employees.party_id')
                    ->limit(1),
                $direction
            ),
            'occupation' => 'occupation',
            'salary' => 'salary',
            'started_on' => 'started_on',
        ];
    }
}
