<?php

namespace App\Services\Reports\Export;

/**
 * Bir raporun formattan bağımsız tanımı; Excel, PDF ve CSV aynı tanımdan üretilir.
 */
final readonly class ReportDocument
{
    /**
     * @param  string  $filename  Uzantısız dosya adı.
     * @param  list<ReportColumn>  $columns
     * @param  list<ReportSection>  $sections
     * @param  array<string, string>  $details  Başlık altında gösterilen filtre özeti (etiket => değer).
     * @param  array<string, ReportCell|string>  $summary  Tablo altındaki özet satırları (etiket => değer).
     */
    public function __construct(
        public string $title,
        public string $subtitle,
        public string $filename,
        public array $columns,
        public array $sections,
        public array $details = [],
        public array $summary = [],
        public bool $landscape = false,
        public string $emptyMessage = 'Bu dönemde hareket yok.',
    ) {}
}
