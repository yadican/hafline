<?php

use App\Support\Database\SchemaRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Free labels on cari cards (e.g. "Büyük müşteri", "Takipte") for listing and filtering only;
 * no business rule reads them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tags', function (Blueprint $table) {
            $table->comment('Cari Etiketleri');
            $table->uuid('id')->primary();
            $table->string('name', 60)->collation(SchemaRules::turkish());
            $table->string('tone', 12)->default('neutral');
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        SchemaRules::uniqueActive('tags', ['name']);
        SchemaRules::checkIn('tags', 'tone', ['neutral', 'green', 'red', 'blue', 'orange']);

        Schema::create('party_tag', function (Blueprint $table) {
            $table->comment('Cari - Etiket');
            $table->foreignUuid('party_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('tag_id')->index()->constrained()->cascadeOnDelete();

            $table->primary(['party_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('party_tag');
        Schema::dropIfExists('tags');
    }
};
