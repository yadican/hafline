<?php

namespace Database\Seeders;

use App\Services\PartyService;
use Illuminate\Database\Seeder;

/**
 * Cari cards the system needs from day one; safe to run again.
 */
class SystemPartySeeder extends Seeder
{
    public function run(PartyService $parties): void
    {
        $parties->temporaryParty();
    }
}
