<?php

use App\Support\Database\SchemaRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dump_site_payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('dump_site_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('payment_type_id')->nullable()->index()->constrained()->restrictOnDelete();
            $table->decimal('amount', 14, 2);
            $table->date('occurred_on');
            $table->string('cheque_no', 20)->nullable();
            $table->string('description')->nullable();
            $table->foreignUuid('created_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('occurred_on');
            $table->index(['dump_site_id', 'occurred_on']);
        });

        SchemaRules::check('dump_site_payments', 'amount', 'amount > 0');

        Schema::create('site_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('site_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('payment_type_id')->nullable()->index()->constrained()->restrictOnDelete();
            $table->decimal('debit', 14, 2)->default(0);
            $table->decimal('credit', 14, 2)->default(0);
            $table->date('occurred_on');
            $table->string('description')->nullable();
            $table->foreignUuid('created_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('occurred_on');
            $table->index(['site_id', 'occurred_on']);
        });

        SchemaRules::checkDebitCredit('site_transactions');

        Schema::create('vehicle_payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('vehicle_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('party_id')->index()->constrained()->restrictOnDelete();
            $table->foreignUuid('payment_type_id')->nullable()->index()->constrained()->restrictOnDelete();
            $table->decimal('amount', 14, 2);
            $table->date('occurred_on');
            $table->string('description')->nullable();
            $table->foreignUuid('company_transaction_id')->nullable()->index()->constrained()->restrictOnDelete();
            $table->foreignUuid('created_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('occurred_on');
            $table->index(['vehicle_id', 'occurred_on']);
        });

        SchemaRules::check('vehicle_payments', 'amount', 'amount > 0');

        Schema::create('vehicle_receivables', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('vehicle_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('party_id')->index()->constrained()->restrictOnDelete();
            $table->foreignUuid('payment_type_id')->nullable()->index()->constrained()->restrictOnDelete();
            $table->decimal('amount', 14, 2);
            $table->date('occurred_on');
            $table->string('description')->nullable();
            $table->foreignUuid('company_transaction_id')->nullable()->index()->constrained()->restrictOnDelete();
            $table->foreignUuid('created_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('occurred_on');
            $table->index(['vehicle_id', 'occurred_on']);
        });

        SchemaRules::check('vehicle_receivables', 'amount', 'amount > 0');

        Schema::create('vehicle_fines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('vehicle_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('party_id')->index()->constrained()->restrictOnDelete();
            $table->foreignUuid('route_id')->nullable()->index()->constrained('haul_routes')->restrictOnDelete();
            $table->decimal('amount', 14, 2);
            $table->date('occurred_on');
            $table->string('document_no', 20)->nullable()->index();
            $table->string('driver_name', 80)->nullable();
            $table->string('status', 10)->default('pending');
            $table->date('paid_on')->nullable();
            $table->foreignUuid('payment_type_id')->nullable()->index()->constrained()->restrictOnDelete();
            $table->string('description')->nullable();
            $table->foreignUuid('created_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('occurred_on');
            $table->index(['vehicle_id', 'occurred_on']);
            $table->index(['vehicle_id', 'paid_on']);
        });

        SchemaRules::check('vehicle_fines', 'amount', 'amount > 0');
        SchemaRules::checkIn('vehicle_fines', 'status', ['pending', 'paid']);
        SchemaRules::check('vehicle_fines', 'paid_on', "(status = 'paid') = (paid_on IS NOT NULL)");

        Schema::create('party_payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('party_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('payment_type_id')->nullable()->index()->constrained()->restrictOnDelete();
            $table->decimal('amount', 14, 2);
            $table->date('occurred_on');
            $table->string('description')->nullable();
            $table->foreignUuid('company_transaction_id')->nullable()->index()->constrained()->restrictOnDelete();
            $table->foreignUuid('created_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('occurred_on');
            $table->index(['party_id', 'occurred_on']);
        });

        SchemaRules::check('party_payments', 'amount', 'amount > 0');

        Schema::create('other_payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('payment_type_id')->nullable()->index()->constrained()->restrictOnDelete();
            $table->decimal('amount', 14, 2);
            $table->date('occurred_on');
            $table->string('description')->nullable();
            $table->foreignUuid('created_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('occurred_on');
        });

        SchemaRules::check('other_payments', 'amount', 'amount > 0');

        Schema::create('cash_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('cash_category_id')->nullable()->index()->constrained()->restrictOnDelete();
            $table->foreignUuid('bank_id')->nullable()->index()->constrained()->restrictOnDelete();
            $table->decimal('debit', 14, 2)->default(0);
            $table->decimal('credit', 14, 2)->default(0);
            $table->date('occurred_on');
            $table->string('description')->nullable();
            $table->foreignUuid('created_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('occurred_on');
        });

        SchemaRules::checkDebitCredit('cash_entries');
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_entries');
        Schema::dropIfExists('other_payments');
        Schema::dropIfExists('party_payments');
        Schema::dropIfExists('vehicle_fines');
        Schema::dropIfExists('vehicle_receivables');
        Schema::dropIfExists('vehicle_payments');
        Schema::dropIfExists('site_transactions');
        Schema::dropIfExists('dump_site_payments');
    }
};
