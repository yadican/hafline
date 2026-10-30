<?php

use App\Enums\InvoiceDirection;
use App\Support\Database\SchemaRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The invoice book (stock-free): a purchase invoice from a supplier or a sale invoice to a
 * customer, with its lines. The header carries the line totals (base, VAT, total); the due date
 * is an optional reminder only. An invoice number is unique per party and direction.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->comment('Faturalar');
            $table->uuid('id')->primary();
            $table->string('direction', 8);
            $table->foreignUuid('party_id')->index()->constrained()->restrictOnDelete();
            $table->string('invoice_no', 30);
            $table->date('issued_on');
            $table->date('due_on')->nullable();
            $table->decimal('subtotal', 14, 2);
            $table->decimal('vat_amount', 14, 2)->default(0);
            $table->decimal('total', 14, 2);
            $table->foreignUuid('site_id')->nullable()->index()->constrained()->restrictOnDelete();
            $table->string('description')->nullable();
            $table->foreignUuid('created_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('issued_on');
            $table->index('due_on');
            $table->index(['party_id', 'issued_on']);
        });

        SchemaRules::checkIn('invoices', 'direction', InvoiceDirection::values());
        SchemaRules::check('invoices', 'amounts', 'subtotal >= 0 AND vat_amount >= 0');
        SchemaRules::check('invoices', 'total', 'total = subtotal + vat_amount');
        SchemaRules::check('invoices', 'due_on', 'due_on IS NULL OR due_on >= issued_on');
        SchemaRules::uniqueActive('invoices', ['party_id', 'direction', 'invoice_no']);

        Schema::create('invoice_lines', function (Blueprint $table) {
            $table->comment('Fatura Satırları');
            $table->uuid('id')->primary();
            $table->foreignUuid('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('description');
            $table->decimal('quantity', 12, 3);
            $table->decimal('unit_price', 14, 4);
            $table->decimal('vat_rate', 5, 2)->default(0);
            $table->decimal('subtotal', 14, 2);
            $table->decimal('vat_amount', 14, 2)->default(0);
            $table->decimal('total', 14, 2);
            $table->foreignUuid('cash_category_id')->nullable()->index()->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['invoice_id', 'sort_order']);
        });

        SchemaRules::check('invoice_lines', 'quantities', 'quantity > 0 AND unit_price >= 0 AND vat_rate >= 0 AND vat_rate <= 100');
        SchemaRules::check('invoice_lines', 'amounts', 'subtotal >= 0 AND vat_amount >= 0');
        SchemaRules::check('invoice_lines', 'total', 'total = subtotal + vat_amount');
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_lines');
        Schema::dropIfExists('invoices');
    }
};
