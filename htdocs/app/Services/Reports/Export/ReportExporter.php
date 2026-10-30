<?php

namespace App\Services\Reports\Export;

use App\Enums\ReportColumnType;
use App\Enums\ReportFormat;
use Barryvdh\DomPDF\Facade\Pdf;
use Dompdf\Dompdf;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExporter
{
    public function __construct(private SpreadsheetReportWriter $spreadsheets) {}

    /**
     * @param  bool  $forPrint  PDF'i indirmek yerine tarayıcıda açar ve yazdırma penceresini tetikler.
     */
    public function download(ReportDocument $document, ReportFormat $format, bool $forPrint = false): Response
    {
        $filename = $document->filename.'.'.$format->value;

        return match ($format) {
            ReportFormat::Xlsx => $this->xlsx($document, $filename),
            ReportFormat::Pdf => $this->pdf($document, $filename, $forPrint),
            ReportFormat::Csv => $this->csv($document, $filename),
        };
    }

    private function xlsx(ReportDocument $document, string $filename): StreamedResponse
    {
        $spreadsheet = $this->spreadsheets->write($document, $this->stamp());

        return response()->streamDownload(function () use ($spreadsheet): void {
            (new Xlsx($spreadsheet))->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function pdf(ReportDocument $document, string $filename, bool $forPrint): Response
    {
        $pdf = Pdf::loadView('admin.reports.export.pdf', [
            'document' => $document,
            'stamp' => $this->stamp(),
            'logo' => $this->logoDataUri(),
        ])
            ->setPaper('a4', $document->landscape ? 'landscape' : 'portrait')
            ->setOption('isFontSubsettingEnabled', true);

        $pdf->render();
        $this->stampPageNumbers($pdf->getDomPDF());

        if (! $forPrint) {
            return $pdf->download($filename);
        }

        // Chrome / Edge PDF görüntüleyicisi açılışta yazdırma penceresini açar; Safari yok sayar.
        $pdf->getDomPDF()->getCanvas()->javascript('print(true);');

        return $pdf->stream($filename);
    }

    /**
     * DomPDF, CSS counter(pages) ile toplam sayfa sayısını yazamıyor; numara render sonrası tuvale basılır.
     */
    private function stampPageNumbers(Dompdf $dompdf): void
    {
        $canvas = $dompdf->getCanvas();
        $metrics = $dompdf->getFontMetrics();
        $font = $metrics->getFont('DejaVu Sans');
        $size = 6.8;
        $millimeter = 72 / 25.4;
        $width = $metrics->getTextWidth('Sayfa 000 / 000', $font, $size);

        $canvas->page_text(
            $canvas->get_width() - 11 * $millimeter - $width,
            $canvas->get_height() - 12.2 * $millimeter,
            'Sayfa {PAGE_NUM} / {PAGE_COUNT}',
            $font,
            $size,
            [0.42, 0.45, 0.5],
        );
    }

    private function csv(ReportDocument $document, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($document): void {
            $handle = fopen('php://output', 'w');

            fwrite($handle, "\xEF\xBB\xBF");

            $this->putCsv($handle, array_map(fn (ReportColumn $column): string => $column->label, $document->columns));

            foreach ($document->sections as $section) {
                if ($section->heading !== null) {
                    $this->putCsv($handle, [$section->heading]);
                }

                foreach ($section->displayRows($document->emptyMessage) as $row) {
                    $this->putCsv($handle, $this->csvCells($document->columns, $row));
                }
            }

            if ($document->summary !== []) {
                $this->putCsv($handle, []);

                foreach ($document->summary as $label => $value) {
                    $this->putCsv($handle, [$label, $value instanceof ReportCell ? $value->plain() : $value]);
                }
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @param  list<ReportColumn>  $columns
     * @return list<string>
     */
    private function csvCells(array $columns, ReportRow $row): array
    {
        $span = $row->labelSpan($columns);
        $cells = [];

        foreach ($columns as $index => $column) {
            $cells[] = match (true) {
                $index === 0 && $span > 0 => (string) $row->label,
                $index < $span => '',
                $column->type === ReportColumnType::Text => $this->neutralizeFormula($column->plain($row->cell($column->key))),
                default => $column->plain($row->cell($column->key)),
            };
        }

        return $cells;
    }

    /**
     * Excel, "=", "+", "-", "@" ile başlayan CSV metnini formül olarak çalıştırır.
     */
    private function neutralizeFormula(string $value): string
    {
        return preg_match('/^[=+\-@\t\r]/', $value) === 1 ? "'".$value : $value;
    }

    /**
     * @param  resource  $handle
     * @param  list<string>  $cells
     */
    private function putCsv($handle, array $cells): void
    {
        fputcsv($handle, $cells, ';', '"', '');
    }

    private function stamp(): string
    {
        $stamp = 'Oluşturulma: '.now()->format('d.m.Y H:i');
        $user = auth()->user()?->name;

        return $user !== null ? $stamp.'   ·   Oluşturan: '.$user : $stamp;
    }

    private function logoDataUri(): ?string
    {
        $path = public_path('img/logo-print.png');

        return is_file($path) ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($path)) : null;
    }
}
