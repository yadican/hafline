<?php

namespace App\Repositories;

use App\Models\DeliveryAddress;
use App\Models\Party;
use App\Support\Sort;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class DeliveryAddressRepository extends Repository
{
    public function __construct(DeliveryAddress $model)
    {
        parent::__construct($model);
    }

    /**
     * @param  array{q?: string, party?: string, active?: string}  $filters
     */
    public function search(array $filters, ?Sort $sort = null, int $perPage = 20): LengthAwarePaginator
    {
        $query = $this->whereTerm($this->query()->with('party'), $filters['q'] ?? null, ['name'])
            ->when($filters['party'] ?? null, fn (Builder $query, string $party) => $query->where('party_id', $party))
            ->when(isset($filters['active']), fn (Builder $query) => $query->where('active', $filters['active'] === '1'));

        return $this->applySort($query, $sort)
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * "Customer · address" labels, so the shipment form picks the customer with the address.
     *
     * @param  string|list<string>|null  $include
     * @return array<string, string>
     */
    public function options(string|array|null $include = null): array
    {
        $include = array_filter((array) $include);

        return $this->query()
            ->withTrashed()
            ->with('party')
            ->where(fn (Builder $query) => $query->where(fn (Builder $query) => $query->where('active', true)->whereNull('deleted_at'))->orWhereIn('id', $include))
            ->get()
            ->mapWithKeys(fn (DeliveryAddress $address): array => [$address->id => $address->party?->name.' · '.$address->name])
            ->sort()
            ->all();
    }

    protected function defaultSort(Builder $query): void
    {
        $query->orderBy('name');
    }

    protected function sortColumns(): array
    {
        return [
            'name' => 'name',
            'party' => fn (Builder $query, string $direction) => $query->orderBy(
                Party::withTrashed()->select('name')->whereColumn('parties.id', 'delivery_addresses.party_id'),
                $direction,
            ),
        ];
    }
}
