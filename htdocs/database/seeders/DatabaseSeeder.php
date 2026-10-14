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
            'username' => 'admin',
            'email' => 'admin@hafline.test',
        ]);

        $this->call([ExampleSeeder::class, HaulSeeder::class]);
    }
}
