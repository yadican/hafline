<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Temporary: dropped once the one-time import from the old hafriyat DB is reconciled.
 * See docs/legacy/db-mapping.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legacy_id_maps', function (Blueprint $table) {
            $table->comment('Eski ID Eşlemeleri');
            $table->uuid('id')->primary();
            $table->string('legacy_table', 64);
            $table->string('legacy_id', 100);
            $table->string('new_table', 64);
            $table->uuid('new_id');
            $table->timestamp('created_at')->nullable();

            $table->unique(['legacy_table', 'legacy_id']);
            $table->index(['new_table', 'new_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legacy_id_maps');
    }
};
