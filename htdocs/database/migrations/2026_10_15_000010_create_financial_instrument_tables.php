<?php

use App\Support\Database\SchemaRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_instruments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('kind', 20);
            $table->string('status', 20)->default('active');
            $table->decimal('debit', 14, 2)->default(0);
            $table->decimal('credit', 14, 2)->default(0);
            $table->date('issued_on')->nullable();
            $table->date('due_on')->nullable();
            $table->foreignUuid('party_id')->nullable()->index()->constrained()->restrictOnDelete();
            $table->string('related_party', 100)->nullable();
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('installment_count')->nullable();
            $table->foreignUuid('bank_id')->nullable()->index()->constrained()->restrictOnDelete();
            $table->string('account_no', 50)->nullable();
            $table->string('document_no', 30)->nullable();
            $table->string('received_no', 30)->nullable();
            $table->string('given_no', 30)->nullable();
            $table->foreignUuid('cash_category_id')->nullable()->index()->constrained()->restrictOnDelete();
            $table->foreignUuid('dump_site_payment_id')->nullable()->index()->constrained()->restrictOnDelete();
            $table->uuid('endorsed_from_id')->nullable()->index();
            $table->foreignUuid('created_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['kind', 'status', 'due_on']);
            $table->index('due_on');
        });

        Schema::table('financial_instruments', function (Blueprint $table) {
            $table->foreign('endorsed_from_id')->references('id')->on('financial_instruments')->restrictOnDelete();
        });

        SchemaRules::checkIn('financial_instruments', 'kind', ['cheque', 'promissory_note', 'loan']);
        SchemaRules::checkIn('financial_instruments', 'status', ['active', 'closed', 'collateral', 'unused']);
        SchemaRules::checkDebitCredit('financial_instruments');
        SchemaRules::uniqueActive('financial_instruments', ['document_no'], 'document_no IS NOT NULL');
        SchemaRules::uniqueActive('financial_instruments', ['received_no'], 'received_no IS NOT NULL');
        SchemaRules::uniqueActive('financial_instruments', ['given_no'], 'given_no IS NOT NULL');

        Schema::create('financial_instrument_installments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('financial_instrument_id')->constrained(indexName: 'fi_installments_instrument_foreign')->cascadeOnDelete();
            $table->decimal('amount', 14, 2);
            $table->date('due_on')->nullable();
            $table->date('paid_on')->nullable();
            $table->string('status', 10)->default('active');
            $table->string('receipt_no', 30)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['financial_instrument_id', 'due_on'], 'fi_installments_instrument_due_on_index');
            $table->index('due_on');
        });

        SchemaRules::checkIn('financial_instrument_installments', 'status', ['active', 'paid']);
        SchemaRules::check('financial_instrument_installments', 'amount', 'amount > 0');

        Schema::create('financial_instrument_attachments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('financial_instrument_id')->index('fi_attachments_instrument_index')->constrained(indexName: 'fi_attachments_instrument_foreign')->cascadeOnDelete();
            $table->string('path');
            $table->string('secondary_path')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_instrument_attachments');
        Schema::dropIfExists('financial_instrument_installments');
        Schema::dropIfExists('financial_instruments');
    }
};
