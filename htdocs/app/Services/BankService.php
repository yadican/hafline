<?php

namespace App\Services;

use App\Cache\BankCache;
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
    public function paginate(array $filters = [], ?Sort $sort = null, int $perPage = BankCache::PER_PAGE): LengthAwarePaginator
    {
        return BankCache::rememberFirstPage(
            $filters,
            $sort,
            fn () => $this->banks->search($filters, $sort, $perPage),
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Bank
    {
        return $this->transaction(function () use ($attributes): Bank {
            /** @var Bank $bank */
            $bank = $this->banks->create($attributes);

            BankCache::flush();

            return $bank;
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Bank $bank, array $attributes): Bank
    {
        return $this->transaction(function () use ($bank, $attributes): Bank {
            /** @var Bank $updated */
            $updated = $this->banks->update($bank, $attributes);

            BankCache::flush();

            return $updated;
        });
    }

    public function delete(Bank $bank): void
    {
        $this->transaction(function () use ($bank): void {
            $this->banks->delete($bank);
            BankCache::flush();
        });
    }
}
