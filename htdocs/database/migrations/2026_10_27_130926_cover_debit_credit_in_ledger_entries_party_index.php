<?php

use App\Support\Database\SchemaRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Party balances (SUM(debit - credit) per party) are read from the index alone, so sorting the
 * party list by balance does not visit every ledger row in the heap.
 */
return new class extends Migration
{
    private const INDEX = 'ledger_entries_party_id_occurred_on_index';

    public function up(): void
    {
        if (! SchemaRules::isPostgres()) {
            return;
        }

        DB::statement('DROP INDEX '.self::INDEX);
        DB::statement('CREATE INDEX '.self::INDEX.' ON ledger_entries (party_id, occurred_on) INCLUDE (debit, credit)');
    }

    public function down(): void
    {
        if (! SchemaRules::isPostgres()) {
            return;
        }

        DB::statement('DROP INDEX '.self::INDEX);
        DB::statement('CREATE INDEX '.self::INDEX.' ON ledger_entries (party_id, occurred_on)');
    }
};
