<?php

namespace App\Cache;

use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Object-scoped form/filter select caches. Each entity has its own key family;
 * flush(SITES) never touches parties/banks/etc.
 */
final class FormOptionsCache
{
    public const TTL_SECONDS = 21600;

    public const SITES = 'sites';

    public const DUMP_SITES = 'dump_sites';

    public const VEHICLE_CLASSES = 'vehicle_classes';

    public const PARTIES = 'parties';

    public const FUEL_STATIONS = 'fuel_stations';

    public const BANKS = 'banks';

    public const CASH_CATEGORIES = 'cash_categories';

    public const EMPLOYEES = 'employees';

    public const HAUL_ROUTES = 'haul_routes';

    public const HAUL_ROUTE_SITES = 'haul_route_sites';

    public const HAUL_ROUTE_DUMPS = 'haul_route_dumps';

    public const COMPANY_GROUPS = 'company_groups';

    public const TAGS = 'tags';

    /**
     * Known static variants per object. Objects listed in DYNAMIC use an epoch
     * so site-scoped (or other unbounded) variants invalidate without enumeration.
     *
     * @var array<string, list<string>>
     */
    private const VARIANTS = [
        self::SITES => ['all', 'active'],
        self::DUMP_SITES => ['all', 'active'],
        self::VEHICLE_CLASSES => ['all', 'active'],
        self::PARTIES => [
            'all',
            'vehicle_owner',
            'machine_operator',
            'fuel',
            'customer',
            'supplier',
            'market',
            'dump_operator',
            'employee',
            'site',
            'quarry',
            'commission',
            'all:company',
        ],
        self::FUEL_STATIONS => ['all', 'active'],
        self::BANKS => ['all', 'active', 'accounts'],
        self::CASH_CATEGORIES => ['all', 'income', 'expense', 'both'],
        self::EMPLOYEES => ['all', 'with_period'],
        self::HAUL_ROUTES => ['site_dump', 'dump_site'],
        self::HAUL_ROUTE_SITES => ['open'],
        self::HAUL_ROUTE_DUMPS => [],
        self::COMPANY_GROUPS => ['all'],
        self::TAGS => ['all'],
    ];

    /** @var list<string> */
    private const DYNAMIC = [
        self::HAUL_ROUTE_DUMPS,
    ];

    /**
     * @param  Closure(): array<string, string>  $callback
     * @return array<string, string>
     */
    public static function remember(string $object, string $variant, Closure $callback): array
    {
        /** @var array<string, string> */
        return Cache::remember(self::key($object, $variant), self::TTL_SECONDS, $callback);
    }

    public static function flush(string ...$objects): void
    {
        foreach ($objects as $object) {
            if (in_array($object, self::DYNAMIC, true)) {
                Cache::forever(self::epochKey($object), self::epoch($object) + 1);

                continue;
            }

            foreach (self::VARIANTS[$object] ?? [] as $variant) {
                Cache::forget(self::key($object, $variant));
            }
        }
    }

    /**
     * Merge edit-form include ids that are missing from a cached options map.
     *
     * @param  array<string, string>  $options
     * @param  string|array<int, string>|null  $include
     * @param  Closure(list<string>): array<string, string>  $missing
     * @return array<string, string>
     */
    public static function withIncludes(array $options, string|array|null $include, Closure $missing): array
    {
        $ids = array_values(array_filter(array_map('strval', (array) $include)));
        $missingIds = array_values(array_diff($ids, array_map('strval', array_keys($options))));

        if ($missingIds === []) {
            return $options;
        }

        $merged = $options + $missing($missingIds);
        asort($merged, SORT_NATURAL | SORT_FLAG_CASE);

        return $merged;
    }

    public static function key(string $object, string $variant): string
    {
        if (in_array($object, self::DYNAMIC, true)) {
            return sprintf('hafLine:form-options:%s:%d:%s', $object, self::epoch($object), $variant);
        }

        return sprintf('hafLine:form-options:%s:%s', $object, $variant);
    }

    private static function epoch(string $object): int
    {
        return (int) Cache::get(self::epochKey($object), 0);
    }

    private static function epochKey(string $object): string
    {
        return sprintf('hafLine:form-options:%s:epoch', $object);
    }
}
