<?php

use App\Support\Database\SchemaRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Transport (nakliye): material is loaded at a quarry or loading place and carried to a customer's
 * delivery address. A shipment debits the customer and credits the carrier (owner of the plate that
 * day), the quarry when we sold the material, and a commission agent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('materials', function (Blueprint $table) {
            $table->comment('Nakliye Malzemeleri');
            $table->uuid('id')->primary();
            $table->string('name', 80)->collation(SchemaRules::turkish());
            $table->string('unit', 5)->default('ton');
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        SchemaRules::uniqueActive('materials', ['name']);
        SchemaRules::checkIn('materials', 'unit', ['ton', 'm3']);

        Schema::create('quarries', function (Blueprint $table) {
            $table->comment('Ocaklar / Yükleme Yerleri');
            $table->uuid('id')->primary();
            $table->string('name')->collation(SchemaRules::turkish());
            $table->foreignUuid('party_id')->nullable()->index()->constrained()->restrictOnDelete();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        SchemaRules::uniqueActive('quarries', ['name']);

        Schema::create('delivery_addresses', function (Blueprint $table) {
            $table->comment('Sevk Yerleri');
            $table->uuid('id')->primary();
            $table->foreignUuid('party_id')->index()->constrained()->restrictOnDelete();
            $table->string('name')->collation(SchemaRules::turkish());
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        SchemaRules::uniqueActive('delivery_addresses', ['party_id', 'name']);

        Schema::create('transport_prices', function (Blueprint $table) {
            $table->comment('Nakliye Fiyat Listeleri');
            $table->uuid('id')->primary();
            $table->string('kind', 10);
            $table->foreignUuid('quarry_id')->nullable()->index()->constrained()->cascadeOnDelete();
            $table->foreignUuid('delivery_address_id')->nullable()->index()->constrained()->cascadeOnDelete();
            $table->foreignUuid('material_id')->nullable()->index()->constrained()->restrictOnDelete();
            $table->decimal('price', 12, 4);
            $table->date('valid_from');
            $table->timestamps();
            $table->softDeletes();
        });

        SchemaRules::checkIn('transport_prices', 'kind', ['quarry', 'sale', 'freight']);
        SchemaRules::check('transport_prices', 'keys', "(kind = 'quarry' AND quarry_id IS NOT NULL AND material_id IS NOT NULL AND delivery_address_id IS NULL)
            OR (kind = 'sale' AND delivery_address_id IS NOT NULL AND material_id IS NOT NULL AND quarry_id IS NULL)
            OR (kind = 'freight' AND quarry_id IS NOT NULL AND delivery_address_id IS NOT NULL AND material_id IS NULL)");
        SchemaRules::check('transport_prices', 'price', 'price > 0');

        Schema::create('shipments', function (Blueprint $table) {
            $table->comment('Nakliye Seferleri');
            $table->uuid('id')->primary();
            $table->string('kind', 15);
            $table->date('shipped_on');
            $table->foreignUuid('vehicle_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('party_id')->index()->constrained()->restrictOnDelete();
            $table->foreignUuid('quarry_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('quarry_party_id')->nullable()->index()->constrained('parties')->restrictOnDelete();
            $table->foreignUuid('material_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('delivery_address_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('customer_party_id')->index()->constrained('parties')->restrictOnDelete();
            $table->decimal('quantity', 10, 3);
            $table->decimal('sale_price', 12, 4);
            $table->decimal('carrier_price', 12, 4);
            $table->decimal('quarry_price', 12, 4)->default(0);
            $table->decimal('vat_rate', 5, 2)->default(0);
            $table->decimal('withholding_rate', 3, 2)->default(0);
            $table->decimal('sale_amount', 14, 2);
            $table->decimal('vat_amount', 14, 2);
            $table->decimal('withholding_amount', 14, 2);
            $table->decimal('customer_amount', 14, 2);
            $table->decimal('carrier_amount', 14, 2);
            $table->decimal('quarry_amount', 14, 2)->default(0);
            $table->foreignUuid('commission_party_id')->nullable()->index()->constrained('parties')->restrictOnDelete();
            $table->decimal('commission_amount', 14, 2)->default(0);
            $table->string('waybill_no', 30)->nullable()->index();
            $table->string('scale_ticket_no', 30)->nullable();
            $table->string('quarry_invoice_no', 30)->nullable();
            $table->string('description')->nullable();
            $table->foreignUuid('created_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('shipped_on');
            $table->index(['vehicle_id', 'shipped_on']);
        });

        SchemaRules::checkIn('shipments', 'kind', ['material_sale', 'transport']);
        SchemaRules::check('shipments', 'quantities', 'quantity > 0 AND sale_price >= 0 AND carrier_price >= 0 AND quarry_price >= 0');
        SchemaRules::check('shipments', 'customer_amount', 'customer_amount = sale_amount + vat_amount - withholding_amount');
        SchemaRules::check('shipments', 'quarry', "(kind = 'material_sale') = (quarry_party_id IS NOT NULL) AND (kind = 'material_sale' OR quarry_amount = 0)");
        SchemaRules::check('shipments', 'commission', '(commission_party_id IS NULL) = (commission_amount = 0) AND commission_amount >= 0');
    }

    public function down(): void
    {
        Schema::dropIfExists('shipments');
        Schema::dropIfExists('transport_prices');
        Schema::dropIfExists('delivery_addresses');
        Schema::dropIfExists('quarries');
        Schema::dropIfExists('materials');
    }
};
