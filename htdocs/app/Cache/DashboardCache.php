<?php

namespace App\Cache;

use Carbon\CarbonInterface;
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

    public static function financeRemindersKey(CarbonInterface $day): string
    {
        return 'finance-reminders:'.$day->toDateString();
    }

    public static function invoiceRemindersKey(CarbonInterface $day): string
    {
        return 'invoice-reminders:'.$day->toDateString();
    }

    /**
     * A cheque or note was added, moved on or taken back: its due reminder must not wait for the TTL.
     */
    public static function forgetFinanceReminders(): void
    {
        Cache::forget(self::PREFIX.self::financeRemindersKey(today()));
    }

    /**
     * An invoice or its payment changed (or a due reminder was closed by hand): Alacaklar / Borçlar
     * must not wait for the TTL.
     */
    public static function forgetInvoiceReminders(): void
    {
        Cache::forget(self::PREFIX.self::invoiceRemindersKey(today()));
    }
}
