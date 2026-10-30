<?php

use App\Enums\InvoiceDirection;
use App\Support\Database\SchemaRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Purchase returns (alış iadesi): same invoice book, stock out at purchase cost, debit the supplier.
 * Optional returns_invoice_id links to an open purchase so the return reduces that invoice's remaining.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('direction', 20)->change();
            $table->foreignUuid('returns_invoice_id')->nullable()->after('direction')->index()->constrained('invoices')->restrictOnDelete();
        });

        if (SchemaRules::isPostgres()) {
            DB::statement('ALTER TABLE invoices DROP CONSTRAINT IF EXISTS invoices_direction_check');
        }

        SchemaRules::checkIn('invoices', 'direction', InvoiceDirection::values());
        // Only a purchase return may point at another invoice (the open purchase it reduces).
        SchemaRules::check('invoices', 'returns_invoice', 'returns_invoice_id IS NULL OR direction = \'purchase_return\'');
    }

    public function down(): void
    {
        if (SchemaRules::isPostgres()) {
            DB::statement('ALTER TABLE invoices DROP CONSTRAINT IF EXISTS invoices_returns_invoice_check');
            DB::statement('ALTER TABLE invoices DROP CONSTRAINT IF EXISTS invoices_direction_check');
        }

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('returns_invoice_id');
            $table->string('direction', 8)->change();
        });

        SchemaRules::checkIn('invoices', 'direction', [InvoiceDirection::Purchase->value, InvoiceDirection::Sale->value]);
    }
};
