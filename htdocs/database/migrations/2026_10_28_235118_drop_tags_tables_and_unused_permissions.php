<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cari tags and the company group screen were withdrawn: roles already classify cari cards.
 * Databases that ran the earlier tags migration lose those tables and the screens' permissions.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('party_tag');
        Schema::dropIfExists('tags');

        $names = [];

        foreach (['company-group', 'tag'] as $resource) {
            foreach (['view', 'create', 'edit', 'delete'] as $action) {
                $names[] = "{$action}-{$resource}";
            }
        }

        $ids = DB::table('permissions')->whereIn('name', $names)->pluck('id');
        DB::table('role_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('model_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
    }

    public function down(): void {}
};
