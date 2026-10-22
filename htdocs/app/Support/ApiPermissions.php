<?php

namespace App\Support;

/**
 * JWT / api guard permissions. Kept separate from the admin (web) catalog.
 */
final class ApiPermissions
{
    public const GUARD = 'api';

    public const FIELD_HAUL_OPERATE = 'field-haul.operate';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::FIELD_HAUL_OPERATE,
        ];
    }
}
