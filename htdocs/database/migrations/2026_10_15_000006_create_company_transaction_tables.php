<?php

use App\Support\Database\SchemaRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('party_id');
            $table->string('role', 20);
            $table->foreignUuid('site_id')->nullable()->index()->constrained()->restrictOnDelete();
            $table->date('occurred_on');
            $table->decimal('debit', 14, 2)->default(0);
            $table->decimal('credit', 14, 2)->default(0);
            $table->foreignUuid('payment_type_id')->nullable()->index()->constrained()->restrictOnDelete();
            $table->string('document_no', 40)->nullable()->index();
            $table->string('description')->nullable();
            $table->foreignUuid('fuel_station_id')->nullable()->index()->constrained()->restrictOnDelete();
            $table->decimal('liters', 10, 2)->nullable();
            $table->decimal('unit_price', 12, 4)->nullable();
            $table->foreignUuid('vehicle_id')->nullable()->index()->constrained()->restrictOnDelete();
            $table->foreignUuid('owner_party_id')->nullable()->index()->constrained('parties')->restrictOnDelete();
            $table->foreignUuid('created_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign(['party_id', 'role'])
                ->references(['party_id', 'role'])
                ->on('party_roles')
                ->restrictOnDelete();

            $table->index(['party_id', 'occurred_on']);
            $table->index(['role', 'occurred_on']);
        });

        SchemaRules::checkIn('company_transactions', 'role', ['supplier', 'customer', 'fuel', 'market']);
        SchemaRules::checkDebitCredit('company_transactions');
        SchemaRules::check('company_transactions', 'fuel_fields', "role = 'fuel' OR (liters IS NULL AND unit_price IS NULL)");
        SchemaRules::check('company_transactions', 'market_fields', "role = 'market' OR (vehicle_id IS NULL AND owner_party_id IS NULL)");
        SchemaRules::check('company_transactions', 'market_station', "role <> 'market' OR fuel_station_id IS NULL");
    }

    public function down(): void
    {
        Schema::dropIfExists('company_transactions');
    }
};
