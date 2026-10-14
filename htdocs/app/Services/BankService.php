<?php

namespace App\Services;

use App\Models\Bank;
use App\Repositories\BankRepository;
use App\Support\Sort;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class BankService extends Service
{
    public function __construct(private BankRepository $banks) {}

    /**
     * @param  array{q?: string, active?: string}  $filters
     */
    public function paginate(array $filters = [], ?Sort $sort = null, int $perPage = 15): LengthAwarePaginator
    {
        return $this->banks->search($filters, $sort, $perPage);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Bank
    {
        /** @var Bank */
        return $this->transaction(fn () => $this->banks->create($attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Bank $bank, array $attributes): Bank
    {
        /** @var Bank */
        return $this->transaction(fn () => $this->banks->update($bank, $attributes));
    }

    public function delete(Bank $bank): void
    {
        $this->transaction(fn () => $this->banks->delete($bank));
    }
}
