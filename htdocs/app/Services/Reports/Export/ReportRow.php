<?php

namespace App\Services\Reports\Export;

use App\Enums\ReportRowKind;

final readonly class ReportRow
{
    /**
     * @param  array<string, mixed>  $cells  Kolon anahtarına göre değerler.
     * @param  ?string  $label  Satırın baştaki boş kolonlarını birleştirerek yazılan etiket (ör. "TOPLAM").
     */
    public function __construct(
        public array $cells,
        public ReportRowKind $kind = ReportRowKind::Data,
        public ?string $label = null,
    ) {}

    /**
     * @param  array<string, mixed>  $cells
     */
    public static function data(array $cells): self
    {
        return new self($cells);
    }

    /**
     * @param  array<string, mixed>  $cells
     */
    public static function opening(array $cells, ?string $label = null): self
    {
        return new self($cells, ReportRowKind::Opening, $label);
    }

    /**
     * @param  array<string, mixed>  $cells
     */
    public static function total(string $label, array $cells): self
    {
        return new self($cells, ReportRowKind::Total, $label);
    }

    public static function note(string $text): self
    {
        return new self([], ReportRowKind::Note, $text);
    }

    public function cell(string $key): mixed
    {
        return $this->cells[$key] ?? null;
    }

    /**
     * Etiketin kapladığı baştaki kolon sayısı; etiket yoksa 0.
     *
     * @param  list<ReportColumn>  $columns
     */
    public function labelSpan(array $columns): int
    {
        if ($this->label === null) {
            return 0;
        }

        foreach ($columns as $index => $column) {
            $value = $this->cell($column->key);

            if ($value !== null && $value !== '') {
                return max(1, $index);
            }
        }

        return count($columns);
    }
}
