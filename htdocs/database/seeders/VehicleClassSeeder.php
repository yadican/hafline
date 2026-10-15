<?php

namespace Database\Seeders;

use App\Models\VehicleClass;
use Illuminate\Database\Seeder;

/**
 * Vehicle types used by the business (from the legacy "Araç tipi" list).
 */
class VehicleClassSeeder extends Seeder
{
    public const NAMES = ['KIRKAYAK', 'MAKİNE', 'ON TEKER', 'TAKSİ', 'TIR'];

    public function run(): void
    {
        foreach (self::NAMES as $name) {
            VehicleClass::query()->firstOrCreate(['name' => $name]);
        }
    }
}
