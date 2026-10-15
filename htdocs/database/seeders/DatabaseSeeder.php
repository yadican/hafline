<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Hafline Yönetici',
            'username' => 'hafline.admin',
            'email' => 'admin@hafline.net',
        ]);

        $this->call([
            LocationSeeder::class,
            VehicleClassSeeder::class,
            CashCategorySeeder::class,
            ExampleSeeder::class,
            HaulSeeder::class,
            MachineWorkSeeder::class,
            FuelReceiptSeeder::class,
            MediumDataSeeder::class,
        ]);
    }
}
