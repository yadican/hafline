<?php

namespace App\Cache;

use App\Support\Sort;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;

final class PartyCache
{
    public const TTL_SECONDS = 3600;

    public const PER_PAGE = 20;

    public const PARTIES_FIRST_PAGE_KEY = 'hafLine:parties-first-page';

    public static function currentPage(): int
    {
        return max(1, (int) request()->input('page', 1));
    }

    public static function firstPageKey(string $type): string
    {
        return self::PARTIES_FIRST_PAGE_KEY.':'.$type;
    }

    /**
     * @param  array{q?: string, type?: string, role?: string, active?: string}  $filters
     */
    public static function hasActiveFilters(array $filters): bool
    {
        foreach ($filters as $key => $value) {
            if ($key === 'type') {
                continue;
            }

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
     * Cache only the unfiltered first page per party type for one hour.
     *
     * @param  array{q?: string, type?: string, role?: string, active?: string}  $filters
     * @param  Closure(): LengthAwarePaginator  $callback
     */
    public static function rememberFirstPage(array $filters, ?Sort $sort, Closure $callback): LengthAwarePaginator
    {
        $type = $filters['type'] ?? null;
        $page = self::currentPage();

        if ($type === null || $page > 1 || $sort !== null || self::hasActiveFilters($filters)) {
            return $callback();
        }

        /** @var LengthAwarePaginator */
        return Cache::remember(self::firstPageKey($type), self::TTL_SECONDS, $callback);
    }

    public static function flush(): void
    {
        self::forget(
            self::PARTIES_FIRST_PAGE_KEY,
            self::firstPageKey('person'),
            self::firstPageKey('company'),
        );
    }

    public static function forget(string ...$keys): void
    {
        foreach ($keys as $key) {
            Cache::forget($key);
        }
    }
}
