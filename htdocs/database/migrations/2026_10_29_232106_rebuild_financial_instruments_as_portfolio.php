<?php

use App\Support\Database\SchemaRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cheques and promissory notes become a portfolio fed by the cari entry that took or gave them.
 * The legacy cheque / note / loan register was never filled, so its tables are replaced: the
 * document stays on the payment, the instrument follows it to collection, endorsement or return.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('financial_instrument_attachments');
        Schema::dropIfExists('financial_instrument_installments');
        Schema::dropIfExists('financial_instruments');

        Schema::create('financial_instruments', function (Blueprint $table) {
            $table->comment('Çek / Senet Portföyü');
            $table->uuid('id')->primary();
            $table->string('payment_method', 20);
            $table->string('direction', 10);
            $table->string('status', 20);
            $table->decimal('amount', 14, 2);
            $table->date('issued_on');
            $table->date('due_on');
            $table->string('number', 30)->nullable();
            $table->string('bank_name', 100)->nullable();
            $table->string('drawer', 120)->nullable();
            $table->foreignUuid('party_id')->nullable()->index()->constrained()->restrictOnDelete();
            $table->string('source_type', 40);
            $table->uuid('source_id');
            $table->string('description')->nullable();
            $table->foreignUuid('created_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'due_on']);
            $table->index('due_on');
        });

        SchemaRules::checkIn('financial_instruments', 'payment_method', ['cheque', 'promissory_note']);
        SchemaRules::checkIn('financial_instruments', 'direction', ['received', 'given']);
        SchemaRules::checkIn('financial_instruments', 'status', ['portfolio', 'in_collection', 'collected', 'endorsed', 'bounced', 'issued', 'paid']);
        SchemaRules::check('financial_instruments', 'amount', 'amount > 0');
        SchemaRules::uniqueActive('financial_instruments', ['source_type', 'source_id']);

        Schema::create('financial_instrument_movements', function (Blueprint $table) {
            $table->comment('Çek / Senet Hareketleri');
            $table->uuid('id')->primary();
            $table->foreignUuid('financial_instrument_id')->constrained(indexName: 'fi_movements_instrument_foreign')->restrictOnDelete();
            $table->string('type', 20);
            $table->date('occurred_on');
            $table->foreignUuid('bank_id')->nullable()->index()->constrained()->restrictOnDelete();
            $table->foreignUuid('party_id')->nullable()->index()->constrained()->restrictOnDelete();
            $table->string('description')->nullable();
            $table->foreignUuid('created_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['financial_instrument_id', 'created_at'], 'fi_movements_instrument_created_index');
        });

        SchemaRules::checkIn('financial_instrument_movements', 'type', ['deposit', 'collect', 'endorse', 'bounce', 'pay']);
        SchemaRules::check('financial_instrument_movements', 'bank', "(type IN ('deposit', 'collect', 'pay')) = (bank_id IS NOT NULL)");
        SchemaRules::check('financial_instrument_movements', 'party', "type <> 'endorse' OR party_id IS NOT NULL");
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_instrument_movements');
        Schema::dropIfExists('financial_instruments');

        (require database_path('migrations/2026_10_15_000010_create_financial_instrument_tables.php'))->up();
    }
};
