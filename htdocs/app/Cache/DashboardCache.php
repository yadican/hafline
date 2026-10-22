<?php

namespace App\Cache;

use Closure;
use Illuminate\Support\Facades\Cache;

final class DashboardCache
{
    public const TTL_SECONDS = 3600;

    public const PREFIX = 'hafLine:dash:';

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public static function remember(string $key, Closure $callback): mixed
    {
        return Cache::remember(self::PREFIX.$key, self::TTL_SECONDS, $callback);
    }
}
