<?php

use App\Support\Database\SchemaRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A fuel source is a station (the fuel belongs to its firm) or our own tank (the fuel is ours, kept
 * as stock). A receipt at a station credits the station's firm at the purchase price while the
 * vehicle owner is debited at the sale price. A tank is filled from a supplier and counted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fuel_stations', function (Blueprint $table) {
            $table->string('type', 10)->default('station')->after('name');
            $table->string('product', 10)->default('diesel')->after('type');
            $table->decimal('purchase_price', 12, 4)->default(0)->after('party_id');
            $table->uuid('party_id')->nullable()->change();
        });

        SchemaRules::checkIn('fuel_stations', 'type', ['station', 'tank']);
        SchemaRules::checkIn('fuel_stations', 'product', ['diesel', 'adblue']);
        SchemaRules::check('fuel_stations', 'party', "(type = 'station') = (party_id IS NOT NULL)");

        Schema::table('fuel_receipts', function (Blueprint $table) {
            $table->decimal('purchase_price', 12, 4)->default(0)->after('unit_price');
            $table->decimal('purchase_amount', 14, 2)->storedAs('round(quantity * purchase_price, 2)')->after('amount');
        });

        if (SchemaRules::isPostgres()) {
            DB::statement('ALTER TABLE fuel_receipts DROP CONSTRAINT IF EXISTS fuel_receipts_fuel_party_check');
        }

        SchemaRules::check('fuel_receipts', 'purchase_price', 'purchase_price >= 0 AND (purchase_price = 0 OR fuel_party_id IS NOT NULL)');

        Schema::create('fuel_tank_fills', function (Blueprint $table) {
            $table->comment('Depo Dolumları');
            $table->uuid('id')->primary();
            $table->foreignUuid('fuel_station_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('party_id')->index()->constrained()->restrictOnDelete();
            $table->date('filled_on');
            $table->decimal('quantity', 10, 2);
            $table->decimal('unit_price', 12, 4);
            $table->decimal('amount', 14, 2)->storedAs('round(quantity * unit_price, 2)');
            $table->string('document_no', 40)->nullable();
            $table->string('description')->nullable();
            $table->foreignUuid('created_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['fuel_station_id', 'filled_on']);
        });

        SchemaRules::check('fuel_tank_fills', 'quantities', 'quantity > 0 AND unit_price > 0');

        Schema::create('fuel_tank_counts', function (Blueprint $table) {
            $table->comment('Depo Sayımları');
            $table->uuid('id')->primary();
            $table->foreignUuid('fuel_station_id')->constrained()->restrictOnDelete();
            $table->date('counted_on');
            $table->decimal('quantity', 10, 2);
            $table->decimal('difference', 10, 2);
            $table->string('description')->nullable();
            $table->foreignUuid('created_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['fuel_station_id', 'counted_on']);
        });

        SchemaRules::check('fuel_tank_counts', 'quantity', 'quantity >= 0');
    }

    public function down(): void
    {
        Schema::dropIfExists('fuel_tank_counts');
        Schema::dropIfExists('fuel_tank_fills');

        if (SchemaRules::isPostgres()) {
            DB::statement('ALTER TABLE fuel_receipts DROP CONSTRAINT IF EXISTS fuel_receipts_purchase_price_check');
            DB::statement('ALTER TABLE fuel_stations DROP CONSTRAINT IF EXISTS fuel_stations_party_check');
            DB::statement('ALTER TABLE fuel_stations DROP CONSTRAINT IF EXISTS fuel_stations_product_check');
            DB::statement('ALTER TABLE fuel_stations DROP CONSTRAINT IF EXISTS fuel_stations_type_check');
        }

        Schema::table('fuel_receipts', function (Blueprint $table) {
            $table->dropColumn(['purchase_amount', 'purchase_price']);
        });

        SchemaRules::check('fuel_receipts', 'fuel_party', '(fuel_station_id IS NULL) = (fuel_party_id IS NULL)');

        Schema::table('fuel_stations', function (Blueprint $table) {
            $table->dropColumn(['type', 'product', 'purchase_price']);
        });
    }
};
