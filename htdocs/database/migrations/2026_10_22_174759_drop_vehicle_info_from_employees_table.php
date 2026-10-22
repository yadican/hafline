<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('employees', 'vehicle_info')) {
            return;
        }

        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('vehicle_info');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('employees', 'vehicle_info')) {
            return;
        }

        Schema::table('employees', function (Blueprint $table) {
            $table->string('vehicle_info')->nullable()->after('started_on');
        });
    }
};
