<?php

namespace App\Console\Commands;

use Database\Seeders\LoadTest\DailyStage;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

#[Signature('simulate:day
    {date? : Simüle edilecek gün (YYYY-MM-DD); varsayılan bugün}
    {--days=1 : Bu günden başlayarak art arda kaç gün simüle edilecek}
    {--no-audit : Sonunda ledger:audit çalıştırma}')]
#[Description('Bir iş gününün sefer, yakıt, makine, cari, kasa ve maaş hareketlerini ekran servisleri üzerinden oluşturur (yalnız test ortamı)')]
class SimulateDayCommand extends Command
{
    public function handle(DailyStage $stage): int
    {
        if (app()->isProduction()) {
            $this->error('Simülasyon üretim ortamında çalıştırılamaz.');

            return self::FAILURE;
        }

        try {
            $first = Carbon::parse($this->argument('date') ?? today()->toDateString())->startOfDay();
        } catch (Throwable) {
            $this->error('Tarih YYYY-MM-DD biçiminde olmalı.');

            return self::FAILURE;
        }

        $days = max(1, (int) $this->option('days'));

        for ($offset = 0; $offset < $days; $offset++) {
            $day = $first->copy()->addDays($offset);

            if ($day->isFuture()) {
                $this->warn($day->format('d.m.Y').' henüz gelmedi; durduruldu.');

                break;
            }

            if (DailyStage::alreadyRan($day)) {
                $this->warn($day->format('d.m.Y').' zaten simüle edilmiş; atlandı.');

                continue;
            }

            $result = DB::transaction(fn () => $stage->run($day));
            $this->report($day, $result);
        }

        if (! $this->option('no-audit')) {
            $this->newLine();

            return $this->call('ledger:audit', ['--skip-reports' => true]);
        }

        return self::SUCCESS;
    }

    /**
     * @param  array{counts: array<string, int>, skipped: list<string>}  $result
     */
    private function report(Carbon $day, array $result): void
    {
        $this->newLine();
        $this->info(sprintf('%s %s%s', $day->format('d.m.Y'), $day->translatedFormat('l'), $day->isToday() ? ' (şu ana kadar: '.now()->format('H:i').')' : ''));

        $rows = collect($result['counts'])->map(fn (int $count, string $label) => [$label, $count])->values()->all();
        $this->table(['Hareket', 'Adet'], $rows === [] ? [['—', 0]] : $rows);

        if ($result['skipped'] !== []) {
            $this->warn(count($result['skipped']).' kayıt kuralların reddiyle girilmedi:');

            foreach (array_slice($result['skipped'], 0, 10) as $reason) {
                $this->line('    '.$reason);
            }
        }
    }
}
