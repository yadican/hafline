<?php

use App\Support\Database\SchemaRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sites', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->collation(SchemaRules::turkish());
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('name');
        });

        Schema::create('dump_sites', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->collation(SchemaRules::turkish());
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('name');
        });

        Schema::create('vehicle_classes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 60)->collation(SchemaRules::turkish());
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        SchemaRules::uniqueActive('vehicle_classes', ['name']);

        Schema::create('vehicle_owners', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('first_name', 80)->collation(SchemaRules::turkish());
            $table->string('last_name', 80)->collation(SchemaRules::turkish());
            $table->string('phone', 20)->nullable();
            $table->string('mobile', 20)->nullable();
            $table->string('address')->nullable();
            $table->foreignUuid('city_id')->nullable()->index()->constrained()->restrictOnDelete();
            $table->foreignUuid('district_id')->nullable()->index()->constrained()->restrictOnDelete();
            $table->foreignUuid('neighborhood_id')->nullable()->index()->constrained()->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['first_name', 'last_name']);
        });

        Schema::create('vehicles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('plate', 20);
            $table->string('plate_key', 20);
            $table->foreignUuid('owner_id')->nullable()->index()->constrained('vehicle_owners')->restrictOnDelete();
            $table->foreignUuid('vehicle_class_id')->nullable()->index()->constrained()->restrictOnDelete();
            $table->string('driver_name', 80)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        SchemaRules::uniqueActive('vehicles', ['plate_key']);

        Schema::create('fuel_stations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->collation(SchemaRules::turkish());
            $table->decimal('unit_price', 12, 4)->default(0);
            $table->foreignUuid('site_id')->nullable()->index()->constrained()->restrictOnDelete();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('name');
        });

        Schema::create('haul_routes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('site_id')->index()->constrained()->restrictOnDelete();
            $table->foreignUuid('dump_site_id')->index()->constrained()->restrictOnDelete();
            $table->decimal('price', 12, 2)->default(0);
            $table->decimal('bonus', 12, 2)->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        SchemaRules::uniqueActive('haul_routes', ['site_id', 'dump_site_id']);

        Schema::create('route_class_prices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('route_id')->index()->constrained('haul_routes')->cascadeOnDelete();
            $table->foreignUuid('vehicle_class_id')->index()->constrained()->restrictOnDelete();
            $table->decimal('price', 12, 2);
            $table->timestamps();
            $table->softDeletes();
        });

        SchemaRules::uniqueActive('route_class_prices', ['route_id', 'vehicle_class_id']);

        Schema::create('route_vehicle_prices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('route_id')->index()->constrained('haul_routes')->cascadeOnDelete();
            $table->foreignUuid('vehicle_id')->index()->constrained()->restrictOnDelete();
            $table->decimal('price', 12, 2);
            $table->timestamps();
            $table->softDeletes();
        });

        SchemaRules::uniqueActive('route_vehicle_prices', ['route_id', 'vehicle_id']);

        Schema::create('dump_site_class_prices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('dump_site_id')->index()->constrained()->cascadeOnDelete();
            $table->foreignUuid('vehicle_class_id')->index()->constrained()->restrictOnDelete();
            $table->decimal('price', 12, 2);
            $table->timestamps();
            $table->softDeletes();
        });

        SchemaRules::uniqueActive('dump_site_class_prices', ['dump_site_id', 'vehicle_class_id'], name: 'dump_site_class_prices_active_unique');
    }

    public function down(): void
    {
        Schema::dropIfExists('dump_site_class_prices');
        Schema::dropIfExists('route_vehicle_prices');
        Schema::dropIfExists('route_class_prices');
        Schema::dropIfExists('haul_routes');
        Schema::dropIfExists('fuel_stations');
        Schema::dropIfExists('vehicles');
        Schema::dropIfExists('vehicle_owners');
        Schema::dropIfExists('vehicle_classes');
        Schema::dropIfExists('dump_sites');
        Schema::dropIfExists('sites');
    }
};
