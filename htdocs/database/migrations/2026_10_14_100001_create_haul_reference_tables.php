<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sites', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('dump_sites', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('vehicle_classes', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('vehicle_owners', function (Blueprint $table) {
            $table->id();
            $table->string('first_name', 80);
            $table->string('last_name', 80);
            $table->string('phone', 20)->nullable();
            $table->timestamps();
        });

        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('plate', 20);
            $table->string('plate_key', 20)->unique();
            $table->foreignId('owner_id')->nullable()->constrained('vehicle_owners')->nullOnDelete();
            $table->foreignId('vehicle_class_id')->nullable()->constrained()->nullOnDelete();
            $table->string('driver_name', 80)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('haul_routes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('dump_site_id')->constrained()->cascadeOnDelete();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['site_id', 'dump_site_id']);
        });

        Schema::create('route_class_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('route_id')->constrained('haul_routes')->cascadeOnDelete();
            $table->foreignId('vehicle_class_id')->constrained()->cascadeOnDelete();
            $table->decimal('price', 12, 2);
            $table->timestamps();

            $table->unique(['route_id', 'vehicle_class_id']);
        });

        Schema::create('route_vehicle_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('route_id')->constrained('haul_routes')->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->decimal('price', 12, 2);
            $table->timestamps();

            $table->unique(['route_id', 'vehicle_id']);
        });

        Schema::create('dump_site_class_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dump_site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_class_id')->constrained()->cascadeOnDelete();
            $table->decimal('price', 12, 2);
            $table->timestamps();

            $table->unique(['dump_site_id', 'vehicle_class_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dump_site_class_prices');
        Schema::dropIfExists('route_vehicle_prices');
        Schema::dropIfExists('route_class_prices');
        Schema::dropIfExists('haul_routes');
        Schema::dropIfExists('vehicles');
        Schema::dropIfExists('vehicle_owners');
        Schema::dropIfExists('vehicle_classes');
        Schema::dropIfExists('dump_sites');
        Schema::dropIfExists('sites');
    }
};
