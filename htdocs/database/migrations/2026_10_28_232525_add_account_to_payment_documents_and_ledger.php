<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A payment names the account the money went through (cash box, bank, POS, credit card), and the
 * cari line it posts carries the same account, so each account's balance is read from its movements.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const DOCUMENTS = [
        'company_transactions',
        'vehicle_payments',
        'vehicle_receivables',
        'party_payments',
        'dump_site_payments',
        'employee_payments',
        'site_transactions',
    ];

    public function up(): void
    {
        foreach (self::DOCUMENTS as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->foreignUuid('bank_id')->nullable()->after('payment_method')->index()->constrained('banks')->restrictOnDelete();
            });
        }

        Schema::table('ledger_entries', function (Blueprint $table) {
            $table->foreignUuid('bank_id')->nullable()->after('payment_method')->constrained('banks')->restrictOnDelete();
            $table->index(['bank_id', 'occurred_on']);
        });
    }

    public function down(): void
    {
        Schema::table('ledger_entries', function (Blueprint $table) {
            $table->dropIndex(['bank_id', 'occurred_on']);
            $table->dropConstrainedForeignId('bank_id');
        });

        foreach (self::DOCUMENTS as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropConstrainedForeignId('bank_id');
            });
        }
    }
};
