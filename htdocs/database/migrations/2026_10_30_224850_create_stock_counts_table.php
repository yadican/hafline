<?php

use App\Support\Database\SchemaRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Physical stock count: counted quantity and difference (counted minus book that day) correct
 * the stock. Own gain/loss — nothing is written to a cari.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_counts', function (Blueprint $table) {
            $table->comment('Stok Sayımları');
            $table->uuid('id')->primary();
            $table->foreignUuid('stock_item_id')->index()->constrained()->restrictOnDelete();
            $table->date('counted_on');
            $table->decimal('quantity', 14, 3);
            $table->decimal('difference', 14, 3);
            $table->string('description')->nullable();
            $table->foreignUuid('created_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('counted_on');
            $table->index(['stock_item_id', 'counted_on']);
        });

        SchemaRules::check('stock_counts', 'quantity', 'quantity >= 0');
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_counts');
    }
};
