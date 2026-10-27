<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('haul_scans', function (Blueprint $table) {
            $table->foreignUuid('fuel_receipt_id')
                ->nullable()
                ->after('machine_work_id')
                ->constrained('fuel_receipts')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('haul_scans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('fuel_receipt_id');
        });
    }
};
