<?php

use App\Support\Database\SchemaRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Banks become the company's money accounts: cash boxes, bank accounts, POS terminals and credit
 * cards, each with the balance it had on the day it was entered. Existing rows are bank accounts.
 * Cash categories can sit under a group (Mazot, Maaş, Banka masrafları...).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('banks', function (Blueprint $table) {
            $table->string('type', 12)->default('bank')->after('id');
            $table->string('branch', 100)->nullable()->after('name');
            $table->string('iban', 34)->nullable()->after('account_no');
            $table->decimal('opening_balance', 14, 2)->default(0)->after('iban');
            $table->date('opened_on')->nullable()->after('opening_balance');

            $table->index('type');
        });

        SchemaRules::checkIn('banks', 'type', ['cash', 'bank', 'pos', 'credit_card']);

        Schema::table('cash_categories', function (Blueprint $table) {
            $table->foreignUuid('parent_id')->nullable()->after('id')->constrained('cash_categories')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cash_categories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_id');
        });

        if (SchemaRules::isPostgres()) {
            DB::statement('ALTER TABLE banks DROP CONSTRAINT IF EXISTS banks_type_check');
        }

        Schema::table('banks', function (Blueprint $table) {
            $table->dropIndex(['type']);
            $table->dropColumn(['type', 'branch', 'iban', 'opening_balance', 'opened_on']);
        });
    }
};
