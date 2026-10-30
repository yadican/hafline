<?php

use App\Support\Database\SchemaRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Money moved between two of our own accounts: cash drawn from the bank, cash deposited, POS
 * takings settled to the bank, the credit card bill paid. No cari is touched; one account goes down
 * and the other goes up by the same amount.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_transfers', function (Blueprint $table) {
            $table->comment('Hesaplar Arası Virmanlar');
            $table->uuid('id')->primary();
            $table->foreignUuid('from_bank_id')->index()->constrained('banks')->restrictOnDelete();
            $table->foreignUuid('to_bank_id')->index()->constrained('banks')->restrictOnDelete();
            $table->date('occurred_on');
            $table->decimal('amount', 14, 2);
            $table->string('description')->nullable();
            $table->foreignUuid('created_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('occurred_on');
        });

        SchemaRules::check('account_transfers', 'amount', 'amount > 0');
        SchemaRules::check('account_transfers', 'accounts', 'from_bank_id <> to_bank_id');
    }

    public function down(): void
    {
        Schema::dropIfExists('account_transfers');
    }
};
