<?php

use App\Enums\CashDirection;
use App\Support\Database\SchemaRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payment methods are not a table: they are the fixed App\Enums\PaymentMethod values stored in
 * each payment row's payment_method column.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_categories', function (Blueprint $table) {
            $table->comment('Nakit Kategorileri');
            $table->uuid('id')->primary();
            $table->string('name', 100)->collation(SchemaRules::turkish());
            $table->string('direction', 10)->default(CashDirection::Both->value);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('name');
        });

        SchemaRules::uniqueActive('cash_categories', ['name']);
        SchemaRules::checkIn('cash_categories', 'direction', CashDirection::values());

        Schema::create('banks', function (Blueprint $table) {
            $table->comment('Bankalar');
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
    }
};
