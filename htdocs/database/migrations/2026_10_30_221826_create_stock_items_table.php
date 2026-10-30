<?php

use App\Enums\StockItemUnit;
use App\Support\Database\SchemaRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stock item cards: name, unit, whether quantity is tracked, optional default expense
 * category, default sales price, and active flag. Movements come in later jobs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_items', function (Blueprint $table) {
            $table->comment('Stok Kalemleri');
            $table->uuid('id')->primary();
            $table->string('name', 100);
            $table->string('unit', 8);
            $table->boolean('tracks_stock')->default(true);
            $table->foreignUuid('cash_category_id')->nullable()->index()->constrained()->restrictOnDelete();
            $table->decimal('sales_price', 14, 4)->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        SchemaRules::checkIn('stock_items', 'unit', StockItemUnit::values());
        SchemaRules::check('stock_items', 'sales_price', 'sales_price >= 0');
        SchemaRules::uniqueActive('stock_items', ['name']);
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_items');
    }
};
