<?php

use App\Support\Database\SchemaRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_groups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('parent_id')->nullable()->index();
            $table->string('name', 100)->collation(SchemaRules::turkish());
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('company_groups', function (Blueprint $table) {
            $table->foreign('parent_id')->references('id')->on('company_groups')->restrictOnDelete();
        });

        SchemaRules::uniqueActive('company_groups', ['parent_id', 'name']);

        Schema::create('companies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_group_id')->nullable()->index()->constrained()->restrictOnDelete();
            $table->string('name')->collation(SchemaRules::turkish());
            $table->text('address')->nullable();
            $table->string('phone', 45)->nullable();
            $table->string('fax', 45)->nullable();
            $table->string('tax_office')->nullable();
            $table->string('tax_number', 20)->nullable()->index();
            $table->foreignUuid('city_id')->nullable()->index()->constrained()->restrictOnDelete();
            $table->foreignUuid('district_id')->nullable()->index()->constrained()->restrictOnDelete();
            $table->foreignUuid('neighborhood_id')->nullable()->index()->constrained()->restrictOnDelete();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('name');
        });

        // No soft deletes: the composite FK from company_transactions needs a plain unique key.
        Schema::create('company_roles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20);
            $table->timestamps();

            $table->unique(['company_id', 'role']);
            $table->index('role');
        });

        SchemaRules::checkIn('company_roles', 'role', ['supplier', 'customer', 'fuel', 'market']);

        Schema::create('company_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->string('role', 20);
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
            $table->foreignUuid('vehicle_owner_id')->nullable()->index()->constrained()->restrictOnDelete();
            $table->foreignUuid('created_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign(['company_id', 'role'])
                ->references(['company_id', 'role'])
                ->on('company_roles')
                ->restrictOnDelete();

            $table->index(['company_id', 'occurred_on']);
            $table->index(['role', 'occurred_on']);
        });

        SchemaRules::checkDebitCredit('company_transactions');
        SchemaRules::check('company_transactions', 'fuel_fields', "role = 'fuel' OR (liters IS NULL AND unit_price IS NULL)");
        SchemaRules::check('company_transactions', 'market_fields', "role = 'market' OR (vehicle_id IS NULL AND vehicle_owner_id IS NULL)");
        SchemaRules::check('company_transactions', 'market_station', "role <> 'market' OR fuel_station_id IS NULL");
    }

    public function down(): void
    {
        Schema::dropIfExists('company_transactions');
        Schema::dropIfExists('company_roles');
        Schema::dropIfExists('companies');
        Schema::dropIfExists('company_groups');
    }
};
