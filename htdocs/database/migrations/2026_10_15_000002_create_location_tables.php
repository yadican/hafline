<?php

use App\Support\Database\SchemaRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 100)->collation(SchemaRules::turkish());
            $table->timestamps();
            $table->softDeletes();
        });

        SchemaRules::uniqueActive('cities', ['name']);

        Schema::create('districts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('city_id')->constrained()->restrictOnDelete();
            $table->string('name', 100)->collation(SchemaRules::turkish());
            $table->timestamps();
            $table->softDeletes();

            $table->index(['city_id', 'name']);
        });

        Schema::create('neighborhoods', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('district_id')->constrained()->restrictOnDelete();
            $table->string('name', 100)->collation(SchemaRules::turkish());
            $table->timestamps();
            $table->softDeletes();

            $table->index(['district_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('neighborhoods');
        Schema::dropIfExists('districts');
        Schema::dropIfExists('cities');
    }
};
