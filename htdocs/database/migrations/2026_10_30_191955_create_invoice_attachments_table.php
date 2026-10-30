<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pictures of a paper invoice (at most four per invoice). Files live on the data disk and are
 * served only to users who can see invoices.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_attachments', function (Blueprint $table) {
            $table->comment('Fatura Dosyaları');
            $table->uuid('id')->primary();
            $table->foreignUuid('invoice_id')->constrained()->restrictOnDelete();
            $table->string('path');
            $table->foreignUuid('created_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['invoice_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_attachments');
    }
};
