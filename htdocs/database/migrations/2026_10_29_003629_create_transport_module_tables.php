<?php

use App\Support\Database\SchemaRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Transport (nakliye) trips are entered freely: the plate (its owner that day is the carrier), the
 * loading place as text, the net weight, the customer and its delivery address, each side's price
 * with VAT and withholding, the driver's name and fee, and an optional referrer. A trip debits the
 * customer and credits the carrier twice on the plate (the trip net and the driver's fee) and the referrer. Delivery addresses belong to a customer and are created from the trip form.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_addresses', function (Blueprint $table) {
            $table->comment('Sevkiyat Adresleri');
            $table->uuid('id')->primary();
            $table->foreignUuid('party_id')->index()->constrained()->restrictOnDelete();
            $table->string('name')->collation(SchemaRules::turkish());
            $table->timestamps();
            $table->softDeletes();
        });

        SchemaRules::uniqueActive('delivery_addresses', ['party_id', 'name']);

        Schema::create('shipments', function (Blueprint $table) {
            $table->comment('Nakliye Seferleri');
            $table->uuid('id')->primary();
            $table->date('shipped_on');
            $table->foreignUuid('party_id')->index()->constrained()->restrictOnDelete();
            $table->foreignUuid('vehicle_id')->constrained()->restrictOnDelete();
            $table->string('driver_name', 80)->nullable();
            $table->decimal('driver_amount', 14, 2)->default(0);
            $table->string('waybill_no', 30)->nullable()->index();
            $table->string('loading_place', 120);
            $table->string('scale_ticket_no', 30)->nullable();
            $table->unsignedInteger('net_kg');
            $table->foreignUuid('customer_party_id')->index()->constrained('parties')->restrictOnDelete();
            $table->foreignUuid('delivery_address_id')->nullable()->constrained()->restrictOnDelete();

            $table->decimal('carrier_price', 12, 4);
            $table->decimal('carrier_vat_rate', 5, 2)->default(0);
            $table->decimal('carrier_withholding_rate', 3, 2)->default(0);
            $table->decimal('carrier_amount', 14, 2);
            $table->decimal('carrier_vat_amount', 14, 2)->default(0);
            $table->decimal('carrier_withholding_amount', 14, 2)->default(0);
            $table->decimal('carrier_net_amount', 14, 2);
            $table->string('carrier_note')->nullable();

            $table->decimal('sale_price', 12, 4);
            $table->decimal('vat_rate', 5, 2)->default(0);
            $table->decimal('withholding_rate', 3, 2)->default(0);
            $table->decimal('sale_amount', 14, 2);
            $table->decimal('vat_amount', 14, 2)->default(0);
            $table->decimal('withholding_amount', 14, 2)->default(0);
            $table->decimal('customer_amount', 14, 2);
            $table->string('customer_note')->nullable();

            $table->foreignUuid('commission_party_id')->nullable()->index()->constrained('parties')->restrictOnDelete();
            $table->decimal('commission_amount', 14, 2)->default(0);
            $table->string('commission_note')->nullable();

            $table->foreignUuid('created_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('shipped_on');
            $table->index(['vehicle_id', 'shipped_on']);
        });

        SchemaRules::check('shipments', 'quantities', 'net_kg > 0 AND carrier_price >= 0 AND sale_price >= 0');
        SchemaRules::check('shipments', 'carrier_net_amount', 'carrier_net_amount = carrier_amount + carrier_vat_amount - carrier_withholding_amount');
        SchemaRules::check('shipments', 'customer_amount', 'customer_amount = sale_amount + vat_amount - withholding_amount');
        SchemaRules::check('shipments', 'commission', '(commission_party_id IS NULL) = (commission_amount = 0) AND commission_amount >= 0');
        SchemaRules::check('shipments', 'driver_amount', 'driver_amount >= 0');
    }

    public function down(): void
    {
        Schema::dropIfExists('shipments');
        Schema::dropIfExists('delivery_addresses');
    }
};
