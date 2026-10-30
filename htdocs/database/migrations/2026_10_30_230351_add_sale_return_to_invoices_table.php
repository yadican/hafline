<?php

use App\Enums\InvoiceDirection;
use App\Support\Database\SchemaRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Sale returns (satış iadesi): stock in at the original sale's frozen unit_cost, credit the customer.
 * Optional returns_invoice_id may point at an open sale (same column as purchase returns).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (SchemaRules::isPostgres()) {
            DB::statement('ALTER TABLE invoices DROP CONSTRAINT IF EXISTS invoices_direction_check');
            DB::statement('ALTER TABLE invoices DROP CONSTRAINT IF EXISTS invoices_returns_invoice_check');
        }

        SchemaRules::checkIn('invoices', 'direction', InvoiceDirection::values());
        SchemaRules::check(
            'invoices',
            'returns_invoice',
            'returns_invoice_id IS NULL OR direction IN (\'purchase_return\', \'sale_return\')',
        );
    }

    public function down(): void
    {
        if (SchemaRules::isPostgres()) {
            DB::statement('ALTER TABLE invoices DROP CONSTRAINT IF EXISTS invoices_returns_invoice_check');
            DB::statement('ALTER TABLE invoices DROP CONSTRAINT IF EXISTS invoices_direction_check');
        }

        SchemaRules::checkIn('invoices', 'direction', [
            InvoiceDirection::Purchase->value,
            InvoiceDirection::Sale->value,
            InvoiceDirection::PurchaseReturn->value,
        ]);
        SchemaRules::check('invoices', 'returns_invoice', 'returns_invoice_id IS NULL OR direction = \'purchase_return\'');
    }
};
