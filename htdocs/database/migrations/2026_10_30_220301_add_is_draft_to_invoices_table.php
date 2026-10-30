<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A draft invoice is saved without posting to cari (or stock). It cannot be paid until approved;
 * uniqueness of party + direction + invoice_no still applies so approve cannot collide.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->boolean('is_draft')->default(false)->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('is_draft');
        });
    }
};
