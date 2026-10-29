<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Rows written by one change of a document share a revision, so a correction reads as one step:
 * the reversed lines are what the document said before, the fresh lines what it says now.
 * Existing corrections are grouped by document and write time; first postings stay without a revision.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ledger_entries', function (Blueprint $table) {
            $table->uuid('revision_id')->nullable()->after('reversal_of')->index();
        });

        DB::table('ledger_entries')
            ->whereNotNull('reversal_of')
            ->select('source_type', 'source_id', 'created_at')
            ->distinct()
            ->orderBy('created_at')
            ->orderBy('source_type')
            ->orderBy('source_id')
            ->lazy(500)
            ->each(fn (object $change) => DB::table('ledger_entries')
                ->where('source_type', $change->source_type)
                ->where('source_id', $change->source_id)
                ->where('created_at', $change->created_at)
                ->update(['revision_id' => (string) Str::uuid()]));
    }

    public function down(): void
    {
        Schema::table('ledger_entries', function (Blueprint $table) {
            $table->dropIndex(['revision_id']);
            $table->dropColumn('revision_id');
        });
    }
};
