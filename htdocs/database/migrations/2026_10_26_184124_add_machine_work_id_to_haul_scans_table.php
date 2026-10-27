<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('haul_scans', function (Blueprint $table) {
            $table->foreignUuid('machine_work_id')
                ->nullable()
                ->after('haul_id')
                ->constrained('machine_works')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('haul_scans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('machine_work_id');
        });
    }
};
