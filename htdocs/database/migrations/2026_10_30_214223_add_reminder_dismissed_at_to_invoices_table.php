<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Manual close for the dashboard due reminder: an unpaid invoice with a due date stays out of
 * Alacaklar / Borçlar once the user dismisses it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->timestamp('reminder_dismissed_at')->nullable()->after('due_on');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('reminder_dismissed_at');
        });
    }
};
