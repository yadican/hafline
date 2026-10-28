<?php

use App\Support\Database\SchemaRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const ROLES = ['vehicle_owner', 'dump_operator', 'customer', 'supplier', 'fuel', 'market', 'employee', 'machine_operator'];

    private const NEW_ROLES = ['site', 'quarry', 'commission'];

    public function up(): void
    {
        $this->replaceCheck([...self::ROLES, ...self::NEW_ROLES]);
    }

    public function down(): void
    {
        DB::table('party_roles')->whereIn('role', self::NEW_ROLES)->delete();

        $this->replaceCheck(self::ROLES);
    }

    /**
     * @param  list<string>  $roles
     */
    private function replaceCheck(array $roles): void
    {
        if (SchemaRules::isPostgres()) {
            DB::statement('ALTER TABLE party_roles DROP CONSTRAINT IF EXISTS party_roles_role_check');
        }

        SchemaRules::checkIn('party_roles', 'role', $roles);
    }
};
