<?php

use App\Support\Database\SchemaRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A carrier that invoices with VAT is owed base + VAT − withholding, like the customer owes us;
 * carrier_amount stays the base (used for profit) and carrier_net_amount is what is credited.
 * Weighed material is entered as net kg from the scale ticket.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->unsignedInteger('net_kg')->nullable()->after('delivery_address_id');
            $table->decimal('carrier_vat_rate', 5, 2)->default(0)->after('withholding_rate');
            $table->decimal('carrier_withholding_rate', 3, 2)->default(0)->after('carrier_vat_rate');
            $table->decimal('carrier_vat_amount', 14, 2)->default(0)->after('carrier_amount');
            $table->decimal('carrier_withholding_amount', 14, 2)->default(0)->after('carrier_vat_amount');
            $table->decimal('carrier_net_amount', 14, 2)->default(0)->after('carrier_withholding_amount');
        });

        DB::table('shipments')->update(['carrier_net_amount' => DB::raw('carrier_amount')]);

        SchemaRules::check('shipments', 'carrier_net_amount', 'carrier_net_amount = carrier_amount + carrier_vat_amount - carrier_withholding_amount');
    }

    public function down(): void
    {
        if (SchemaRules::isPostgres()) {
            DB::statement('ALTER TABLE shipments DROP CONSTRAINT IF EXISTS shipments_carrier_net_amount_check');
        }

        Schema::table('shipments', function (Blueprint $table) {
            $table->dropColumn(['net_kg', 'carrier_vat_rate', 'carrier_withholding_rate', 'carrier_vat_amount', 'carrier_withholding_amount', 'carrier_net_amount']);
        });
    }
};
