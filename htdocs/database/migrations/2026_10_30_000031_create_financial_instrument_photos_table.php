<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pictures of a cheque or note (front, back, endorsement). Files live on the data disk and are
 * served only to users who can see the portfolio.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_instrument_photos', function (Blueprint $table) {
            $table->comment('Çek / Senet Resimleri');
            $table->uuid('id')->primary();
            $table->foreignUuid('financial_instrument_id')->constrained(indexName: 'fi_photos_instrument_foreign')->restrictOnDelete();
            $table->string('path');
            $table->foreignUuid('created_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['financial_instrument_id', 'created_at'], 'fi_photos_instrument_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_instrument_photos');
    }
};
