<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A match released because its cheque or note returned unpaid names that step, so taking the step
 * back brings exactly those matches back, and not ones removed by hand or by an edited payment.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_payments', function (Blueprint $table) {
            $table->foreignUuid('released_by_movement_id')->nullable()->after('created_by')->index()
                ->constrained('financial_instrument_movements')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invoice_payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('released_by_movement_id');
        });
    }
};
