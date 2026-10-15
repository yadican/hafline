<?php

use App\Support\Database\SchemaRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every account holder (person or company) is a party; balances, ownership and payments
 * always point at parties.id, while persons/companies hold the type-specific details.
 */
return new class extends Migration
{
    private const ROLES = ['vehicle_owner', 'dump_operator', 'customer', 'supplier', 'fuel', 'market', 'employee'];

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

        Schema::create('parties', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type', 10);
            $table->string('name', 200)->collation(SchemaRules::turkish());
            $table->string('phone', 20)->nullable();
            $table->string('mobile', 20)->nullable();
            $table->string('email', 120)->nullable();
            $table->text('address')->nullable();
            $table->foreignUuid('city_id')->nullable()->index()->constrained()->restrictOnDelete();
            $table->foreignUuid('district_id')->nullable()->index()->constrained()->restrictOnDelete();
            $table->foreignUuid('neighborhood_id')->nullable()->index()->constrained()->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('name');
            $table->index('type');
        });

        SchemaRules::checkIn('parties', 'type', ['person', 'company']);

        Schema::create('persons', function (Blueprint $table) {
            $table->foreignUuid('party_id')->primary()->constrained()->cascadeOnDelete();
            $table->string('first_name', 80)->collation(SchemaRules::turkish());
            $table->string('last_name', 80)->collation(SchemaRules::turkish());
            $table->string('national_id', 11)->nullable()->index();
            $table->timestamps();
        });

        Schema::create('companies', function (Blueprint $table) {
            $table->foreignUuid('party_id')->primary()->constrained()->cascadeOnDelete();
            $table->foreignUuid('company_group_id')->nullable()->index()->constrained()->restrictOnDelete();
            $table->string('title', 200)->collation(SchemaRules::turkish());
            $table->string('tax_office', 100)->nullable();
            $table->string('tax_number', 20)->nullable()->index();
            $table->string('fax', 20)->nullable();
            $table->timestamps();
        });

        // No soft deletes: company_transactions references (party_id, role) with a composite FK.
        Schema::create('party_roles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('party_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20);
            $table->timestamps();

            $table->unique(['party_id', 'role']);
            $table->index('role');
        });

        SchemaRules::checkIn('party_roles', 'role', self::ROLES);
    }

    public function down(): void
    {
        Schema::dropIfExists('party_roles');
        Schema::dropIfExists('companies');
        Schema::dropIfExists('persons');
        Schema::dropIfExists('parties');
        Schema::dropIfExists('company_groups');
    }
};
