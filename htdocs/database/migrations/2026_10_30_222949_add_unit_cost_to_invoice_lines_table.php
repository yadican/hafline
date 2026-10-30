<?php

use App\Support\Database\SchemaRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sale lines that reduce stock freeze the weighted average unit cost of that day on the line so
 * later purchases do not rewrite the cost of goods sold.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_lines', function (Blueprint $table) {
            $table->decimal('unit_cost', 14, 4)->nullable()->after('stock_item_id');
        });

        SchemaRules::check('invoice_lines', 'unit_cost', 'unit_cost IS NULL OR unit_cost >= 0');
    }

    public function down(): void
    {
        if (SchemaRules::isPostgres()) {
            DB::statement('ALTER TABLE invoice_lines DROP CONSTRAINT IF EXISTS invoice_lines_unit_cost_check');
        }

        Schema::table('invoice_lines', function (Blueprint $table) {
            $table->dropColumn('unit_cost');
        });
    }
};
