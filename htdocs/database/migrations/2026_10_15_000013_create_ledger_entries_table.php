<?php

use App\Support\Database\SchemaRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only party ledger. Perspective is the counterparty's account: debit = the party owes
 * us / we paid it, credit = we owe the party. Balance = SUM(debit) - SUM(credit).
 * Rows are never edited or deleted; a change posts a reversal (reversal_of) plus new lines.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('party_id')->constrained()->restrictOnDelete();
            $table->date('occurred_on');
            $table->decimal('debit', 14, 2)->default(0);
            $table->decimal('credit', 14, 2)->default(0);
            $table->string('description')->nullable();
            $table->string('source_type', 40);
            $table->uuid('source_id');
            $table->foreignUuid('vehicle_id')->nullable()->constrained()->restrictOnDelete();
            $table->uuid('reversal_of')->nullable()->unique();
            $table->foreignUuid('created_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['party_id', 'occurred_on']);
            $table->index(['source_type', 'source_id']);
            $table->index(['vehicle_id', 'occurred_on']);
        });

        Schema::table('ledger_entries', function (Blueprint $table) {
            $table->foreign('reversal_of')->references('id')->on('ledger_entries')->restrictOnDelete();
        });

        SchemaRules::checkDebitCredit('ledger_entries');
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_entries');
    }
};
