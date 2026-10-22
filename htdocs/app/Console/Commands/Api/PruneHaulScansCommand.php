<?php

namespace App\Console\Commands\Api;

use App\Services\Api\FieldHaulService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('hauls:prune-scans')]
#[Description('Delete expired pending haul plate scans and their private files')]
class PruneHaulScansCommand extends Command
{
    public function handle(FieldHaulService $fieldHauls): int
    {
        $count = $fieldHauls->pruneExpired();
        $this->info("Pruned {$count} expired haul scan(s).");

        return self::SUCCESS;
    }
}
