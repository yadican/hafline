<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('field_haul_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('site_id')->constrained('sites');
            $table->foreignUuid('dump_site_id')->constrained('dump_sites');
            $table->string('shift', 20);
            $table->timestamp('locked_at');
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'closed_at']);
        });

        Schema::table('hauls', function (Blueprint $table) {
            $table->foreignUuid('field_haul_session_id')
                ->nullable()
                ->after('created_by')
                ->constrained('field_haul_sessions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('hauls', function (Blueprint $table) {
            $table->dropConstrainedForeignId('field_haul_session_id');
        });

        Schema::dropIfExists('field_haul_sessions');
    }
};
