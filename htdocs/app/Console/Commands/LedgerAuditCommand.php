<?php

namespace App\Console\Commands;

use App\Services\Ledger\LedgerAudit;
use App\Services\Ledger\ReportChainAudit;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('ledger:audit
    {--limit=5 : Her kontrol için gösterilecek uyumsuzluk sayısı}
    {--skip-reports : Raporların ay ay devir zincirini atla (hızlı kontrol)}')]
#[Description('Cari (ledger) ile kaynak belgeleri, devir kapanışlarını, bakiye raporlarını, kasayı ve raporların ay ay devir zincirini karşılaştırır')]
class LedgerAuditCommand extends Command
{
    public function handle(LedgerAudit $audit, ReportChainAudit $chains): int
    {
        $this->line('<options=bold>Cari ve belgeler</>');
        $failed = $this->print($audit->run());

        if (! $this->option('skip-reports')) {
            $this->newLine();
            $this->line('<options=bold>Raporlar: başlangıçtan bugüne ay ay</>');
            $failed = $this->print($chains->run()) || $failed;
        }

        $this->newLine();

        if ($failed) {
            $this->error('Cari ile kaynaklar arasında uyumsuzluk var.');

            return self::FAILURE;
        }

        $this->info('Cari, belgeler ve raporlar birebir tutuyor.');

        return self::SUCCESS;
    }

    /**
     * @param  list<array{check: string, checked: int, failures: list<string>}>  $results
     */
    private function print(array $results): bool
    {
        $failed = false;
        $limit = max(0, (int) $this->option('limit'));

        foreach ($results as $result) {
            $count = count($result['failures']);
            $failed = $failed || $count > 0;

            $this->line(sprintf(
                '%s %s · kontrol %s · uyumsuz %d',
                $count > 0 ? '<fg=red>✗</>' : '<fg=green>✓</>',
                $result['check'],
                number_format($result['checked'], 0, ',', '.'),
                $count,
            ));

            foreach (array_slice($result['failures'], 0, $limit) as $failure) {
                $this->line('    '.$failure);
            }
        }

        return $failed;
    }
}
