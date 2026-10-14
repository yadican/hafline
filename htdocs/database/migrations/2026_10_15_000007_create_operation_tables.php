<?php

use App\Support\Database\SchemaRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const SHIFTS = ['day', 'night'];

    public function up(): void
    {
        Schema::create('hauls', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('ticket_no', 20)->index();
            $table->foreignUuid('vehicle_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('owner_id')->nullable()->index()->constrained('vehicle_owners')->restrictOnDelete();
            $table->foreignUuid('route_id')->constrained('haul_routes')->restrictOnDelete();
            $table->foreignUuid('site_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('dump_site_id')->constrained()->restrictOnDelete();
            $table->string('driver_name', 80)->nullable();
            $table->unsignedInteger('tonnage')->default(0);
            $table->decimal('price', 12, 2)->default(0);
            $table->decimal('dump_price', 12, 2)->default(0);
            $table->string('shift', 10);
            $table->dateTime('hauled_at')->index();
            $table->foreignUuid('created_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('created_at');
            $table->index(['vehicle_id', 'hauled_at']);
            $table->index(['route_id', 'hauled_at']);
            $table->index(['site_id', 'hauled_at']);
            $table->index(['dump_site_id', 'hauled_at']);
        });

        SchemaRules::checkIn('hauls', 'shift', self::SHIFTS);
        SchemaRules::check('hauls', 'prices', 'price >= 0 AND dump_price >= 0');

        Schema::create('haul_dumps', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('haul_id')->nullable()->index()->constrained()->restrictOnDelete();
            $table->foreignUuid('vehicle_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('dump_site_id')->constrained()->restrictOnDelete();
            $table->decimal('price', 12, 2)->default(0);
            $table->unsignedInteger('tonnage')->default(0);
            $table->date('dumped_on');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['vehicle_id', 'dumped_on']);
            $table->index(['dump_site_id', 'dumped_on']);
        });

        Schema::create('machine_loadings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('ticket_no', 20)->nullable()->index();
            $table->foreignUuid('vehicle_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('owner_id')->nullable()->index()->constrained('vehicle_owners')->restrictOnDelete();
            $table->foreignUuid('site_id')->constrained()->restrictOnDelete();
            $table->decimal('price', 12, 2)->default(0);
            $table->decimal('dump_price', 12, 2)->default(0);
            $table->string('driver_name', 80)->nullable();
            $table->string('shift', 10);
            $table->unsignedInteger('hours')->default(0);
            $table->date('loaded_on');
            $table->foreignUuid('created_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['vehicle_id', 'loaded_on']);
            $table->index(['site_id', 'loaded_on']);
        });

        SchemaRules::checkIn('machine_loadings', 'shift', self::SHIFTS);

        Schema::create('fuel_receipts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('ticket_no', 20)->nullable()->index();
            $table->foreignUuid('vehicle_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('owner_id')->nullable()->index()->constrained('vehicle_owners')->restrictOnDelete();
            $table->foreignUuid('fuel_station_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignUuid('site_id')->nullable()->constrained()->restrictOnDelete();
            $table->decimal('quantity', 10, 2);
            $table->decimal('unit_price', 12, 4);
            $table->decimal('amount', 14, 2)->storedAs('round(quantity * unit_price, 2)');
            $table->date('issued_on');
            $table->unsignedInteger('km')->nullable();
            $table->string('received_by', 80)->nullable();
            $table->string('shift', 10)->nullable();
            $table->unsignedSmallInteger('haul_ticket_count')->nullable();
            $table->foreignUuid('created_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('issued_on');
            $table->index(['vehicle_id', 'issued_on']);
            $table->index(['fuel_station_id', 'issued_on']);
            $table->index(['site_id', 'issued_on']);
        });

        SchemaRules::checkIn('fuel_receipts', 'shift', self::SHIFTS, nullable: true);
        SchemaRules::check('fuel_receipts', 'quantities', 'quantity >= 0 AND unit_price >= 0');

        Schema::create('ticket_handovers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('vehicle_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignUuid('route_id')->nullable()->constrained('haul_routes')->restrictOnDelete();
            $table->unsignedSmallInteger('haul_count')->nullable();
            $table->unsignedSmallInteger('fuel_count')->nullable();
            $table->text('description')->nullable();
            $table->date('handed_on');
            $table->string('shift', 10)->nullable();
            $table->foreignUuid('created_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('handed_on');
            $table->index(['vehicle_id', 'handed_on']);
            $table->index(['route_id', 'handed_on']);
        });

        SchemaRules::checkIn('ticket_handovers', 'shift', self::SHIFTS, nullable: true);

        Schema::create('dump_checks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('route_id')->nullable()->constrained('haul_routes')->restrictOnDelete();
            $table->unsignedSmallInteger('haul_count')->nullable();
            $table->text('description')->nullable();
            $table->date('checked_on');
            $table->string('shift', 10)->nullable();
            $table->foreignUuid('user_id')->nullable()->index()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('checked_on');
            $table->index(['route_id', 'checked_on']);
        });

        SchemaRules::checkIn('dump_checks', 'shift', self::SHIFTS, nullable: true);

        Schema::create('fuel_checks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('fuel_station_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('haul_count')->nullable();
            $table->unsignedSmallInteger('fuel_count')->nullable();
            $table->text('description')->nullable();
            $table->date('checked_on');
            $table->string('shift', 10)->nullable();
            $table->foreignUuid('user_id')->nullable()->index()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('checked_on');
            $table->index(['fuel_station_id', 'checked_on']);
        });

        SchemaRules::checkIn('fuel_checks', 'shift', self::SHIFTS, nullable: true);
    }

    public function down(): void
    {
        Schema::dropIfExists('fuel_checks');
        Schema::dropIfExists('dump_checks');
        Schema::dropIfExists('ticket_handovers');
        Schema::dropIfExists('fuel_receipts');
        Schema::dropIfExists('machine_loadings');
        Schema::dropIfExists('haul_dumps');
        Schema::dropIfExists('hauls');
    }
};
