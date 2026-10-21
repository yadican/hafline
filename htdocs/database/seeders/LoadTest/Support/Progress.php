<?php

namespace Database\Seeders\LoadTest\Support;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Console progress for long load-test seed stages.
 */
final class Progress
{
    private float $stageStartedAt;

    private int $lastPct = -1;

    public function __construct(private ?Command $command)
    {
        $this->stageStartedAt = microtime(true);
    }

    public function stage(string $title): void
    {
        $this->stageStartedAt = microtime(true);
        $this->lastPct = -1;
        $this->command?->newLine();
        $this->command?->info('▶ '.$title);
    }

    public function note(string $message): void
    {
        $this->command?->line('  · '.$message);
    }

    public function done(string $summary = ''): void
    {
        $elapsed = number_format(microtime(true) - $this->stageStartedAt, 1);
        $suffix = $summary !== '' ? " — {$summary}" : '';
        $this->command?->info("  ✓ tamamlandı ({$elapsed}s){$suffix}");
    }

    public function batch(int $done, int $total, string $label = ''): void
    {
        if ($this->command === null || $total <= 0) {
            return;
        }

        $pct = (int) floor(($done / $total) * 100);

        if ($pct === $this->lastPct && $done < $total) {
            return;
        }

        $this->lastPct = $pct;
        $prefix = $label !== '' ? "{$label}: " : '';
        $this->command->getOutput()->write("\r  {$prefix}".number_format($done).'/'.number_format($total)." (%{$pct})");

        if ($done >= $total) {
            $this->command->newLine();
            $this->lastPct = -1;
        }
    }

    public function banner(string $title, Carbon $from, Carbon $to): void
    {
        $this->command?->newLine();
        $this->command?->warn(str_repeat('═', 64));
        $this->command?->warn("  {$title}");
        $this->command?->warn('  Dönem: '.$from->toDateString().' → '.$to->toDateString());
        $this->command?->warn(str_repeat('═', 64));
    }
}
