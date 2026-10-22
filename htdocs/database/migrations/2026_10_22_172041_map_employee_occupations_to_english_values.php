<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Map legacy free-text Turkish titles to EmployeeOccupation enum values.
     */
    public function up(): void
    {
        $map = [
            'Şoför' => 'driver',
            'Operatör' => 'operator',
            'Formen' => 'foreman',
            'Usta' => 'craftsman',
            'Bekçi' => 'guard',
            'Muhasebe' => 'accounting',
            'Depo' => 'warehouse',
            'Ofis' => 'office',
            'Puantör' => 'timekeeper',
            'Saha sorumlusu' => 'site_supervisor',
        ];

        foreach ($map as $turkish => $english) {
            DB::table('employees')->where('occupation', $turkish)->update(['occupation' => $english]);
        }
    }

    public function down(): void
    {
        $map = [
            'driver' => 'Şoför',
            'operator' => 'Operatör',
            'foreman' => 'Formen',
            'craftsman' => 'Usta',
            'guard' => 'Bekçi',
            'accounting' => 'Muhasebe',
            'warehouse' => 'Depo',
            'office' => 'Ofis',
            'timekeeper' => 'Puantör',
            'site_supervisor' => 'Saha sorumlusu',
        ];

        foreach ($map as $english => $turkish) {
            DB::table('employees')->where('occupation', $english)->update(['occupation' => $turkish]);
        }
    }
};
