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

        // The composite FK requires the party to hold the role it is linked with.
        Schema::create('site_parties', function (Blueprint $table) {
            $table->foreignUuid('site_id')->constrained()->cascadeOnDelete();
            $table->uuid('party_id');
            $table->string('role', 20);
            $table->timestamps();

            $table->primary(['site_id', 'party_id', 'role']);
            $table->foreign(['party_id', 'role'])
                ->references(['party_id', 'role'])
                ->on('party_roles')
                ->restrictOnDelete();
            $table->index(['party_id', 'role']);
        });

        SchemaRules::checkIn('site_parties', 'role', ['customer', 'supplier', 'fuel', 'market']);

        Schema::create('dump_sites', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->collation(SchemaRules::turkish());
            $table->foreignUuid('party_id')->index()->constrained()->restrictOnDelete();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('name');
        });

        Schema::create('vehicle_classes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 60)->collation(SchemaRules::turkish());
            $table->boolean('hourly')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        SchemaRules::uniqueActive('vehicle_classes', ['name']);

        Schema::create('vehicles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('plate', 20);
            $table->string('plate_key', 20);
            $table->foreignUuid('vehicle_class_id')->nullable()->index()->constrained()->restrictOnDelete();
            $table->string('driver_name', 80)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        SchemaRules::uniqueActive('vehicles', ['plate_key']);

        // Both dates are inclusive; an open period (ended_on NULL) is the current owner.
        Schema::create('vehicle_ownerships', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('vehicle_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('party_id')->index()->constrained()->restrictOnDelete();
            $table->date('started_on');
            $table->date('ended_on')->nullable();
            $table->timestamps();

            $table->index(['vehicle_id', 'started_on']);
        });

        SchemaRules::check('vehicle_ownerships', 'dates', 'ended_on IS NULL OR ended_on >= started_on');
        SchemaRules::excludeOverlap('vehicle_ownerships', 'vehicle_id', 'started_on', 'ended_on');

        // Hourly rate periods of machines; same inclusive, contiguous periods as ownerships.
        Schema::create('vehicle_rates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('vehicle_id')->constrained()->restrictOnDelete();
            $table->decimal('hourly_rate', 12, 2);
            $table->date('started_on');
            $table->date('ended_on')->nullable();
            $table->timestamps();

            $table->index(['vehicle_id', 'started_on']);
        });

        SchemaRules::check('vehicle_rates', 'dates', 'ended_on IS NULL OR ended_on >= started_on');
        SchemaRules::check('vehicle_rates', 'hourly_rate', 'hourly_rate >= 0');
        SchemaRules::excludeOverlap('vehicle_rates', 'vehicle_id', 'started_on', 'ended_on');

        Schema::create('fuel_stations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->collation(SchemaRules::turkish());
            $table->foreignUuid('party_id')->index()->constrained()->restrictOnDelete();
            $table->decimal('unit_price', 12, 4)->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('name');
        });

        Schema::create('fuel_station_sites', function (Blueprint $table) {
            $table->foreignUuid('fuel_station_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('site_id')->index()->constrained()->cascadeOnDelete();

            $table->primary(['fuel_station_id', 'site_id']);
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
        Schema::dropIfExists('fuel_station_sites');
        Schema::dropIfExists('fuel_stations');
        Schema::dropIfExists('vehicle_rates');
        Schema::dropIfExists('vehicle_ownerships');
        Schema::dropIfExists('vehicles');
        Schema::dropIfExists('vehicle_classes');
        Schema::dropIfExists('dump_sites');
        Schema::dropIfExists('site_parties');
        Schema::dropIfExists('sites');
    }
};
