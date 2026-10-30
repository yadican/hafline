<?php

namespace App\Services\Reports\Export;

use App\Enums\ReportRowKind;

final readonly class ReportSection
{
    /**
     * @param  list<ReportRow>  $rows
     */
    public function __construct(
        public array $rows,
        public ?string $heading = null,
    ) {}

    /**
     * Hareket yoksa toplam satırlarından önce bilgi satırı ekler.
     *
     * @return list<ReportRow>
     */
    public function displayRows(string $emptyMessage): array
    {
        $hasData = collect($this->rows)->contains(fn (ReportRow $row): bool => $row->kind === ReportRowKind::Data);

        if ($hasData) {
            return $this->rows;
        }

        $firstTotal = collect($this->rows)->search(fn (ReportRow $row): bool => $row->kind === ReportRowKind::Total);
        $position = $firstTotal === false ? count($this->rows) : $firstTotal;

        return array_values(array_merge(
            array_slice($this->rows, 0, $position),
            [ReportRow::note($emptyMessage)],
            array_slice($this->rows, $position),
        ));
    }
}
