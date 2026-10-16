<?php

use App\Support\Database\SchemaRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Codes are the official ones (plate code for cities); they identify rows when the data is refreshed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cities', function (Blueprint $table) {
            $table->comment('Şehirler');
            $table->uuid('id')->primary();
            $table->unsignedSmallInteger('code')->unique();
            $table->string('name', 100)->collation(SchemaRules::turkish());
            $table->timestamps();
            $table->softDeletes();
        });

        SchemaRules::uniqueActive('cities', ['name']);

        Schema::create('districts', function (Blueprint $table) {
            $table->comment('İlçeler');
            $table->uuid('id')->primary();
            $table->foreignUuid('city_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('code')->unique();
            $table->string('name', 100)->collation(SchemaRules::turkish());
            $table->timestamps();
            $table->softDeletes();

            $table->index(['city_id', 'name']);
        });

        Schema::create('neighborhoods', function (Blueprint $table) {
            $table->comment('Mahalleler');
            $table->uuid('id')->primary();
            $table->foreignUuid('district_id')->constrained()->restrictOnDelete();
            $table->string('name', 100)->collation(SchemaRules::turkish());
            $table->string('type', 10)->default('mahalle');
            $table->string('postal_code', 10)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['district_id', 'name', 'type']);
        });

        SchemaRules::checkIn('neighborhoods', 'type', ['mahalle', 'koy', 'belde']);
    }

    public function down(): void
    {
        Schema::dropIfExists('neighborhoods');
        Schema::dropIfExists('districts');
        Schema::dropIfExists('cities');
    }
};
