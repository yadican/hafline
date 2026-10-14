<?php

namespace App\Support\Database;

use Illuminate\Support\Facades\DB;

class SchemaRules
{
    public const TURKISH_COLLATION = 'tr-TR-x-icu';

    public static function isPostgres(): bool
    {
        return DB::getDriverName() === 'pgsql';
    }

    public static function turkish(): ?string
    {
        return self::isPostgres() ? self::TURKISH_COLLATION : null;
    }

    /**
     * Soft-deleted rows must not block re-creating the same record.
     *
     * @param  list<string>  $columns
     */
    public static function uniqueActive(string $table, array $columns, ?string $where = null, ?string $name = null): void
    {
        $name ??= $table.'_'.implode('_', $columns).'_active_unique';
        $condition = 'deleted_at IS NULL'.($where ? ' AND '.$where : '');

        DB::statement(sprintf(
            'CREATE UNIQUE INDEX %s ON %s (%s) WHERE %s',
            $name,
            $table,
            implode(', ', $columns),
            $condition,
        ));
    }

    public static function check(string $table, string $name, string $expression): void
    {
        if (! self::isPostgres()) {
            return;
        }

        DB::statement(sprintf(
            'ALTER TABLE %s ADD CONSTRAINT %s_%s_check CHECK (%s)',
            $table,
            $table,
            $name,
            $expression,
        ));
    }

    /**
     * @param  list<string>  $values
     */
    public static function checkIn(string $table, string $column, array $values, bool $nullable = false): void
    {
        $list = implode(', ', array_map(fn (string $value) => "'{$value}'", $values));
        $expression = "{$column} IN ({$list})".($nullable ? " OR {$column} IS NULL" : '');

        self::check($table, $column, $expression);
    }

    public static function checkDebitCredit(string $table): void
    {
        self::check($table, 'debit_credit', '(debit > 0) <> (credit > 0) AND debit >= 0 AND credit >= 0');
    }
}
