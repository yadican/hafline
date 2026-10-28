<?php

use App\Support\Database\SchemaRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cari-level documents: a party's balance brought in from before the system, and a transfer
 * (virman) that debits one party and credits another by the same amount without moving cash.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opening_balances', function (Blueprint $table) {
            $table->comment('Açılış Bakiyeleri');
            $table->uuid('id')->primary();
            $table->foreignUuid('party_id')->constrained()->restrictOnDelete();
            $table->date('occurred_on');
            $table->decimal('debit', 14, 2)->default(0);
            $table->decimal('credit', 14, 2)->default(0);
            $table->string('description')->nullable();
            $table->foreignUuid('created_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('occurred_on');
        });

        SchemaRules::checkDebitCredit('opening_balances');
        SchemaRules::uniqueActive('opening_balances', ['party_id']);

        Schema::create('party_transfers', function (Blueprint $table) {
            $table->comment('Cari Virmanlar');
            $table->uuid('id')->primary();
            $table->foreignUuid('debit_party_id')->index()->constrained('parties')->restrictOnDelete();
            $table->foreignUuid('credit_party_id')->index()->constrained('parties')->restrictOnDelete();
            $table->date('occurred_on');
            $table->decimal('amount', 14, 2);
            $table->string('description')->nullable();
            $table->foreignUuid('created_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('occurred_on');
        });

        SchemaRules::check('party_transfers', 'amount', 'amount > 0');
        SchemaRules::check('party_transfers', 'parties', 'debit_party_id <> credit_party_id');
    }

    public function down(): void
    {
        Schema::dropIfExists('party_transfers');
        Schema::dropIfExists('opening_balances');
    }
};
