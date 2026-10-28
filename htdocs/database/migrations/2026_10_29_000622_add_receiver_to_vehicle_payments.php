<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('vehicle_payments', function (Blueprint $table) {
            $table->string('receiver_name', 120)->nullable()->after('description');
            $table->string('receiver_national_id', 11)->nullable()->after('receiver_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vehicle_payments', function (Blueprint $table) {
            $table->dropColumn(['receiver_name', 'receiver_national_id']);
        });
    }
};
