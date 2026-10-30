<?php

use App\Support\Database\SchemaRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which payment (a cari entry that paid the invoice's party) settles how much of an invoice. One
 * payment can be split over several invoices and one invoice paid by several payments; a match
 * never exceeds the invoice's remaining amount or the payment's unmatched amount. A released match
 * is soft-deleted, so it can be brought back (a bounced cheque taken back).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_payments', function (Blueprint $table) {
            $table->comment('Fatura Ödeme Eşleşmeleri');
            $table->uuid('id')->primary();
            $table->foreignUuid('invoice_id')->constrained()->restrictOnDelete();
            $table->string('payment_type', 40);
            $table->uuid('payment_id');
            $table->decimal('amount', 14, 2);
            $table->foreignUuid('created_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('invoice_id');
            $table->index(['payment_type', 'payment_id']);
        });

        SchemaRules::check('invoice_payments', 'amount', 'amount > 0');
        SchemaRules::uniqueActive('invoice_payments', ['invoice_id', 'payment_type', 'payment_id']);
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_payments');
    }
};
