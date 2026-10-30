<?php

use App\Support\Database\SchemaRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Own-use stock exit: item leaves stock at that day's average cost with no cari party.
 * Cost is an expense attributed to a site and/or one of our vehicles (category for reporting).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_usages', function (Blueprint $table) {
            $table->comment('Stok Kullanımları');
            $table->uuid('id')->primary();
            $table->foreignUuid('stock_item_id')->index()->constrained()->restrictOnDelete();
            $table->decimal('quantity', 14, 3);
            $table->decimal('unit_cost', 14, 4);
            $table->decimal('amount', 14, 2);
            $table->date('used_on');
            $table->foreignUuid('site_id')->nullable()->index()->constrained()->restrictOnDelete();
            $table->foreignUuid('vehicle_id')->nullable()->index()->constrained()->restrictOnDelete();
            $table->foreignUuid('cash_category_id')->index()->constrained()->restrictOnDelete();
            $table->string('description')->nullable();
            $table->foreignUuid('created_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('used_on');
        });

        SchemaRules::check('stock_usages', 'quantity', 'quantity > 0');
        SchemaRules::check('stock_usages', 'unit_cost', 'unit_cost >= 0');
        SchemaRules::check('stock_usages', 'amount', 'amount >= 0');
        SchemaRules::check('stock_usages', 'target', 'site_id IS NOT NULL OR vehicle_id IS NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_usages');
    }
};
