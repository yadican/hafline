<?php

namespace App\Services\Ledger;

/**
 * Collects the comparisons of one report's month-by-month walk.
 */
final class ChainChecks
{
    private int $checked = 0;

    /** @var list<string> */
    private array $failures = [];

    /**
     * The month opens where the previous one closed; the first month has nothing to compare.
     */
    public function opening(string $label, ?float $previousClosing, float $opening): void
    {
        if ($previousClosing !== null) {
            $this->compare($label.' devir', $previousClosing, $opening);
        }
    }

    public function closing(string $label, float $expected, float $actual): void
    {
        $this->compare($label, $expected, $actual);
    }

    /**
     * @param  list<array{debit: float|null, credit: float|null}>  $rows
     */
    public function rows(string $label, array $rows, float $totalDebit, float $totalCredit): void
    {
        $this->compare($label.' borç toplamı', array_sum(array_map(fn (array $row) => (float) ($row['debit'] ?? 0), $rows)), $totalDebit);
        $this->compare($label.' alacak toplamı', array_sum(array_map(fn (array $row) => (float) ($row['credit'] ?? 0), $rows)), $totalCredit);
    }

    /**
     * @return array{check: string, checked: int, failures: list<string>}
     */
    public function result(string $check): array
    {
        return ['check' => $check, 'checked' => $this->checked, 'failures' => $this->failures];
    }

    private function compare(string $label, float $expected, float $actual): void
    {
        $this->checked++;

        if (abs(round($expected, 2) - round($actual, 2)) >= 0.005) {
            $this->failures[] = sprintf('%s: beklenen %s, raporda %s', $label, number_format($expected, 2, ',', '.'), number_format($actual, 2, ',', '.'));
        }
    }
}
