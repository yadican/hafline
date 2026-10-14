<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hauls', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_no', 20)->index();
            $table->foreignId('vehicle_id')->constrained()->restrictOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('vehicle_owners')->nullOnDelete();
            $table->foreignId('route_id')->constrained('haul_routes')->restrictOnDelete();
            $table->foreignId('site_id')->constrained()->restrictOnDelete();
            $table->foreignId('dump_site_id')->constrained()->restrictOnDelete();
            $table->string('driver_name', 80)->nullable();
            $table->unsignedInteger('tonnage')->default(0);
            $table->decimal('price', 12, 2)->default(0);
            $table->decimal('dump_price', 12, 2)->default(0);
            $table->string('shift', 10);
            $table->dateTime('hauled_at')->index();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hauls');
    }
};
