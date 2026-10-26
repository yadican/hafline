<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('field_haul_sessions', function (Blueprint $table) {
            $table->uuid('dump_site_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('field_haul_sessions')->whereNull('dump_site_id')->delete();

        Schema::table('field_haul_sessions', function (Blueprint $table) {
            $table->uuid('dump_site_id')->nullable(false)->change();
        });
    }
};
