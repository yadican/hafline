<?php

use App\Support\Database\SchemaRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A party the system itself relies on (e.g. the temporary cari for unregistered plates) is found by
 * its key, not by name, and cannot be renamed, deactivated or deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parties', function (Blueprint $table) {
            $table->string('system_key', 30)->nullable()->after('type');
        });

        SchemaRules::uniqueActive('parties', ['system_key'], 'system_key IS NOT NULL');
    }

    public function down(): void
    {
        Schema::table('parties', function (Blueprint $table) {
            $table->dropIndex('parties_system_key_active_unique');
            $table->dropColumn('system_key');
        });
    }
};
