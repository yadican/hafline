<?php

use App\Support\Database\SchemaRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Plate sales (Kime: Plaka): the invoice debits the owner on that date and ties the cari line to
 * the vehicle. Only sale invoices may carry a vehicle_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignUuid('vehicle_id')->nullable()->after('party_id')->index()->constrained()->restrictOnDelete();
        });

        SchemaRules::check(
            'invoices',
            'vehicle_sale',
            'vehicle_id IS NULL OR direction = \'sale\'',
        );
    }

    public function down(): void
    {
        if (SchemaRules::isPostgres()) {
            DB::statement('ALTER TABLE invoices DROP CONSTRAINT IF EXISTS invoices_vehicle_sale_check');
        }

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('vehicle_id');
        });
    }
};
