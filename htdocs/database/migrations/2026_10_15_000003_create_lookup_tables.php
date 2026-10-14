<?php

use App\Support\Database\SchemaRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 50)->collation(SchemaRules::turkish());
            $table->string('kind', 20);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        SchemaRules::uniqueActive('payment_types', ['name']);
        SchemaRules::checkIn('payment_types', 'kind', ['cash', 'cheque', 'bank', 'other']);

        Schema::create('cash_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 100)->collation(SchemaRules::turkish());
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('name');
        });

        Schema::create('banks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 100)->collation(SchemaRules::turkish());
            $table->string('representative', 100)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('account_no', 50)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banks');
        Schema::dropIfExists('cash_categories');
        Schema::dropIfExists('payment_types');
    }
};
