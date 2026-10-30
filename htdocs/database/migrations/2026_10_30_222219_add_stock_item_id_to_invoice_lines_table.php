<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A purchase invoice line may point at a stock item; when the item tracks stock and the invoice
 * is live (not draft), the line's quantity enters stock at unit cost = subtotal / quantity.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_lines', function (Blueprint $table) {
            $table->foreignUuid('stock_item_id')->nullable()->after('cash_category_id')->index()->constrained()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invoice_lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('stock_item_id');
        });
    }
};
