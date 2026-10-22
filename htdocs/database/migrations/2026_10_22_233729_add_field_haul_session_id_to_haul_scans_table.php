<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('haul_scans', function (Blueprint $table) {
            $table->foreignUuid('field_haul_session_id')
                ->nullable()
                ->after('user_id')
                ->constrained('field_haul_sessions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('haul_scans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('field_haul_session_id');
        });
    }
};
