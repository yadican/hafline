<?php

namespace App\Cache;

use App\Support\Sort;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;

final class StockItemCache
{
    public const TTL_SECONDS = 3600;

    public const PER_PAGE = 20;

    public const FIRST_PAGE_KEY = 'hafLine:stock-items-first-page';

    public static function currentPage(): int
    {
        return max(1, (int) request()->input('page', 1));
    }

    /**
     * @param  array{q?: string, unit?: string, tracks_stock?: string, active?: string}  $filters
     */
    public static function hasActiveFilters(array $filters): bool
    {
        foreach ($filters as $value) {
            if (is_array($value)) {
                if ($value !== []) {
                    return true;
                }

                continue;
            }

            if ($value !== null && $value !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * Cache only the unfiltered first page for one hour.
     *
     * @param  array{q?: string, unit?: string, tracks_stock?: string, active?: string}  $filters
     * @param  Closure(): LengthAwarePaginator  $callback
     */
    public static function rememberFirstPage(array $filters, ?Sort $sort, Closure $callback): LengthAwarePaginator
    {
        $page = self::currentPage();

        if ($page > 1 || $sort !== null || self::hasActiveFilters($filters)) {
            return $callback();
        }

        /** @var LengthAwarePaginator */
        return Cache::remember(self::FIRST_PAGE_KEY, self::TTL_SECONDS, $callback);
    }

    public static function flush(): void
    {
        self::forget(self::FIRST_PAGE_KEY);
    }

    public static function forget(string ...$keys): void
    {
        foreach ($keys as $key) {
            Cache::forget($key);
        }
    }
}
