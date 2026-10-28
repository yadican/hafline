<?php

use App\Support\Database\SchemaRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Every posting carries the line of business and the site of the document that posted it, so the
 * cari can be read per module and per site. Existing rows take both from their source document.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ledger_entries', function (Blueprint $table) {
            $table->string('module', 12)->default('general')->after('cash_category_id');
            $table->foreignUuid('site_id')->nullable()->after('module')->constrained()->restrictOnDelete();
            $table->index(['site_id', 'occurred_on']);
        });

        SchemaRules::checkIn('ledger_entries', 'module', ['excavation', 'transport', 'fuel', 'general']);

        DB::table('ledger_entries')
            ->whereIn('source_type', ['haul', 'machine_work', 'dump_site_payment'])
            ->update(['module' => 'excavation']);

        DB::table('ledger_entries')
            ->where('source_type', 'fuel_receipt')
            ->update(['module' => 'fuel']);

        DB::table('ledger_entries')
            ->where('source_type', 'company_transaction')
            ->whereIn('source_id', DB::table('company_transactions')->where('role', 'fuel')->select('id'))
            ->update(['module' => 'fuel']);

        foreach (['haul' => 'hauls', 'machine_work' => 'machine_works', 'fuel_receipt' => 'fuel_receipts', 'company_transaction' => 'company_transactions'] as $type => $table) {
            DB::table('ledger_entries')
                ->where('source_type', $type)
                ->update(['site_id' => DB::raw("(select {$table}.site_id from {$table} where {$table}.id = ledger_entries.source_id)")]);
        }
    }

    public function down(): void
    {
        if (SchemaRules::isPostgres()) {
            DB::statement('ALTER TABLE ledger_entries DROP CONSTRAINT IF EXISTS ledger_entries_module_check');
        }

        Schema::table('ledger_entries', function (Blueprint $table) {
            $table->dropIndex(['site_id', 'occurred_on']);
            $table->dropConstrainedForeignId('site_id');
            $table->dropColumn('module');
        });
    }
};
