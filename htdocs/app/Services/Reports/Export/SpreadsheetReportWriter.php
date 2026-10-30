<?php

namespace App\Services\Reports\Export;

use App\Enums\ReportColumnType;
use App\Enums\ReportRowKind;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SpreadsheetReportWriter
{
    private const COLOR_INK = '2A2E32';

    private const COLOR_STEEL = '0E1114';

    private const COLOR_MUTED = '6B7280';

    private const COLOR_OCHRE = 'C4892E';

    private const COLOR_OCHRE_SOFT = 'F3EBE0';

    private const COLOR_SOIL_INK = '6B4515';

    private const COLOR_LINE = 'D5D8DE';

    private const COLOR_ROW_LINE = 'ECEEF2';

    private const COLOR_ZEBRA = 'FAFAFB';

    private const COLOR_CANVAS = 'F3F4F6';

    private const COLOR_DEBIT = 'A2452F';

    private const COLOR_CREDIT = '3D6340';

    private const LOGO_HEIGHT_PX = 52;

    public function write(ReportDocument $document, string $stamp): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getProperties()
            ->setCreator((string) config('app.name'))
            ->setTitle($document->title)
            ->setSubject($document->subtitle);
        $spreadsheet->getDefaultStyle()->getFont()->setName('Calibri')->setSize(10);

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($this->sheetTitle($document->title));
        $sheet->setShowGridlines(false);

        $columnCount = max(1, count($document->columns));
        $lastColumn = Coordinate::stringFromColumnIndex($columnCount);

        $row = $this->writeHeading($sheet, $document, $stamp, $lastColumn);
        $headerRow = $row;
        $this->writeColumnHeaders($sheet, $document->columns, $headerRow, $lastColumn);
        $row++;

        $filterEnd = null;

        foreach ($document->sections as $sectionIndex => $section) {
            if ($sectionIndex > 0) {
                $row++;
            }

            if ($section->heading !== null) {
                $this->writeSectionHeading($sheet, $section->heading, $row, $lastColumn);
                $row++;
            }

            $zebra = false;

            foreach ($section->displayRows($document->emptyMessage) as $reportRow) {
                $this->writeRow($sheet, $document->columns, $reportRow, $row, $lastColumn, $zebra);

                if ($reportRow->kind === ReportRowKind::Data) {
                    $zebra = ! $zebra;
                }

                if ($reportRow->kind !== ReportRowKind::Total) {
                    $filterEnd = $row;
                }

                $row++;
            }
        }

        $this->applyColumnLayout($sheet, $document, $headerRow, $row - 1);

        if (count($document->sections) === 1 && $filterEnd !== null && $filterEnd > $headerRow) {
            $sheet->setAutoFilter("A{$headerRow}:{$lastColumn}{$filterEnd}");
        }

        $this->writeSummary($sheet, $document->summary, $row + 1, $columnCount);

        $sheet->freezePane('A'.($headerRow + 1));
        $this->configurePrint($sheet, $document, $headerRow);
        $sheet->setSelectedCell('A1');

        return $spreadsheet;
    }

    private function writeHeading(Worksheet $sheet, ReportDocument $document, string $stamp, string $lastColumn): int
    {
        $sheet->getRowDimension(1)->setRowHeight(self::LOGO_HEIGHT_PX * 0.75 + 8);
        $this->placeLogo($sheet);

        $lines = [
            [$document->title, ['bold' => true, 'size' => 16, 'color' => ['rgb' => self::COLOR_STEEL]], 24],
            [$document->subtitle, ['size' => 11, 'color' => ['rgb' => self::COLOR_INK]], 18],
        ];

        if ($document->details !== []) {
            $details = collect($document->details)
                ->map(fn (string $value, string $label): string => $label.': '.$value)
                ->implode('   ·   ');
            $lines[] = [$details, ['size' => 9, 'color' => ['rgb' => self::COLOR_MUTED]], 15];
        }

        $lines[] = [$stamp, ['size' => 9, 'italic' => true, 'color' => ['rgb' => self::COLOR_MUTED]], 15];

        $row = 2;

        foreach ($lines as [$text, $font, $height]) {
            $sheet->setCellValueExplicit("A{$row}", $text, DataType::TYPE_STRING);
            $sheet->mergeCells("A{$row}:{$lastColumn}{$row}");
            $sheet->getStyle("A{$row}")->applyFromArray([
                'font' => $font,
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            ]);
            $sheet->getRowDimension($row)->setRowHeight($height);
            $row++;
        }

        $sheet->getStyle('A'.($row - 1).":{$lastColumn}".($row - 1))->applyFromArray([
            'borders' => ['bottom' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => self::COLOR_OCHRE]]],
        ]);

        $sheet->getRowDimension($row)->setRowHeight(8);

        return $row + 1;
    }

    private function placeLogo(Worksheet $sheet): void
    {
        $path = public_path('img/logo-print.png');

        if (! is_file($path)) {
            return;
        }

        $drawing = new Drawing;
        $drawing->setName('Logo');
        $drawing->setPath($path);
        $drawing->setResizeProportional(true);
        $drawing->setHeight(self::LOGO_HEIGHT_PX);
        $drawing->setCoordinates('A1');
        $drawing->setOffsetX(2);
        $drawing->setOffsetY(4);
        $drawing->setWorksheet($sheet);
    }

    /**
     * @param  list<ReportColumn>  $columns
     */
    private function writeColumnHeaders(Worksheet $sheet, array $columns, int $row, string $lastColumn): void
    {
        foreach ($columns as $index => $column) {
            $coordinate = Coordinate::stringFromColumnIndex($index + 1).$row;
            $sheet->setCellValueExplicit($coordinate, $column->label, DataType::TYPE_STRING);
            $sheet->getStyle($coordinate)->getAlignment()->setHorizontal($this->horizontalAlignment($column));
        }

        $sheet->getRowDimension($row)->setRowHeight(22);
        $sheet->getStyle("A{$row}:{$lastColumn}{$row}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR_INK]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders' => ['bottom' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => self::COLOR_OCHRE]]],
        ]);
    }

    private function writeSectionHeading(Worksheet $sheet, string $heading, int $row, string $lastColumn): void
    {
        $sheet->setCellValueExplicit("A{$row}", $heading, DataType::TYPE_STRING);
        $sheet->mergeCells("A{$row}:{$lastColumn}{$row}");
        $sheet->getRowDimension($row)->setRowHeight(20);
        $sheet->getStyle("A{$row}:{$lastColumn}{$row}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => self::COLOR_SOIL_INK]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR_OCHRE_SOFT]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
    }

    /**
     * @param  list<ReportColumn>  $columns
     */
    private function writeRow(Worksheet $sheet, array $columns, ReportRow $reportRow, int $row, string $lastColumn, bool $zebra): void
    {
        $span = $reportRow->labelSpan($columns);

        if ($span > 0) {
            $sheet->setCellValueExplicit("A{$row}", (string) $reportRow->label, DataType::TYPE_STRING);
            $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(
                $reportRow->kind === ReportRowKind::Note ? Alignment::HORIZONTAL_CENTER : Alignment::HORIZONTAL_LEFT,
            );

            if ($span > 1) {
                $sheet->mergeCells("A{$row}:".Coordinate::stringFromColumnIndex($span).$row);
            }
        }

        foreach ($columns as $index => $column) {
            if ($index < $span) {
                continue;
            }

            $coordinate = Coordinate::stringFromColumnIndex($index + 1).$row;
            $this->writeCell($sheet, $coordinate, $column, $reportRow->cell($column->key));
            $sheet->getStyle($coordinate)->getAlignment()->setHorizontal($this->horizontalAlignment($column));
        }

        $range = "A{$row}:{$lastColumn}{$row}";

        $style = match ($reportRow->kind) {
            ReportRowKind::Data => [
                'borders' => ['bottom' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => self::COLOR_ROW_LINE]]],
            ],
            ReportRowKind::Opening => [
                'font' => ['italic' => true, 'color' => ['rgb' => self::COLOR_INK]],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR_CANVAS]],
                'borders' => ['bottom' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => self::COLOR_LINE]]],
            ],
            ReportRowKind::Total => [
                'font' => ['bold' => true, 'color' => ['rgb' => self::COLOR_STEEL]],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR_OCHRE_SOFT]],
                'borders' => [
                    'top' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => self::COLOR_INK]],
                    'bottom' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => self::COLOR_LINE]],
                ],
            ],
            ReportRowKind::Note => [
                'font' => ['italic' => true, 'color' => ['rgb' => self::COLOR_MUTED]],
            ],
        };

        if ($reportRow->kind === ReportRowKind::Data && $zebra) {
            $style['fill'] = ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR_ZEBRA]];
        }

        $sheet->getStyle($range)->applyFromArray($style);
        $sheet->getRowDimension($row)->setRowHeight($reportRow->kind === ReportRowKind::Total ? 20 : 17);
    }

    private function writeCell(Worksheet $sheet, string $coordinate, ReportColumn $column, mixed $value): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if ($column->type === ReportColumnType::Text) {
            $sheet->setCellValueExplicit($coordinate, (string) $value, DataType::TYPE_STRING);

            return;
        }

        if ($column->type === ReportColumnType::Date) {
            $sheet->setCellValueExplicit($coordinate, ExcelDate::dateTimeToExcel(Carbon::parse($value)), DataType::TYPE_NUMERIC);
            $sheet->getStyle($coordinate)->getNumberFormat()->setFormatCode('dd.mm.yyyy');

            return;
        }

        $number = $column->numericValue($value);
        $sheet->setCellValueExplicit($coordinate, $number, DataType::TYPE_NUMERIC);

        if ($column->type === ReportColumnType::Balance) {
            $sheet->getStyle($coordinate)->getNumberFormat()->setFormatCode('#,##0.00 "TL B";#,##0.00 "TL A";#,##0.00 "TL"');

            if (round($number, 2) !== 0.0) {
                $sheet->getStyle($coordinate)->getFont()->getColor()->setRGB($number > 0 ? self::COLOR_DEBIT : self::COLOR_CREDIT);
            }

            return;
        }

        $sheet->getStyle($coordinate)->getNumberFormat()->setFormatCode(
            $this->numberFormat($column->decimalsFor($value), $column->suffixFor($value)),
        );
    }

    /**
     * @param  array<string, ReportCell|string>  $summary
     */
    private function writeSummary(Worksheet $sheet, array $summary, int $row, int $columnCount): void
    {
        if ($summary === []) {
            return;
        }

        $valueEndIndex = max(2, $columnCount);
        $valueStartIndex = max(2, $valueEndIndex - 1);
        $labelColumn = Coordinate::stringFromColumnIndex($valueStartIndex - 1);
        $valueColumn = Coordinate::stringFromColumnIndex($valueStartIndex);
        $valueEndColumn = Coordinate::stringFromColumnIndex($valueEndIndex);
        $first = $row;

        foreach ($summary as $label => $value) {
            $sheet->setCellValueExplicit("{$labelColumn}{$row}", $label, DataType::TYPE_STRING);

            if ($value instanceof ReportCell) {
                $sheet->setCellValueExplicit("{$valueColumn}{$row}", (float) $value->value, DataType::TYPE_NUMERIC);
                $sheet->getStyle("{$valueColumn}{$row}")->getNumberFormat()
                    ->setFormatCode($this->numberFormat($value->decimals ?? 2, $value->suffix));
            } else {
                $sheet->setCellValueExplicit("{$valueColumn}{$row}", $value, DataType::TYPE_STRING);
            }

            if ($valueStartIndex < $valueEndIndex) {
                $sheet->mergeCells("{$valueColumn}{$row}:{$valueEndColumn}{$row}");
            }

            $sheet->getStyle("{$labelColumn}{$row}:{$valueEndColumn}{$row}")->applyFromArray([
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
                'borders' => ['bottom' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => self::COLOR_ROW_LINE]]],
            ]);
            $sheet->getStyle("{$labelColumn}{$row}")->getFont()->setBold(true)->getColor()->setRGB(self::COLOR_MUTED);
            $sheet->getStyle("{$valueColumn}{$row}")->getFont()->setBold(true);
            $row++;
        }

        $last = $row - 1;
        $sheet->getStyle("{$labelColumn}{$first}:{$valueEndColumn}{$first}")->applyFromArray([
            'borders' => ['top' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => self::COLOR_OCHRE]]],
        ]);
        $sheet->getStyle("{$labelColumn}{$last}:{$valueEndColumn}{$last}")->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR_OCHRE_SOFT]],
        ]);
    }

    private function applyColumnLayout(Worksheet $sheet, ReportDocument $document, int $headerRow, int $lastRow): void
    {
        foreach ($document->columns as $index => $column) {
            $letter = Coordinate::stringFromColumnIndex($index + 1);
            $sheet->getColumnDimension($letter)->setWidth($column->width ?? $this->contentWidth($document, $column));

            if ($lastRow <= $headerRow) {
                continue;
            }

            $sheet->getStyle("{$letter}".($headerRow + 1).":{$letter}{$lastRow}")->getAlignment()
                ->setVertical(Alignment::VERTICAL_CENTER)
                ->setWrapText($column->type === ReportColumnType::Text);
        }
    }

    private function contentWidth(ReportDocument $document, ReportColumn $column): int
    {
        $longest = mb_strlen($column->label);

        foreach ($document->sections as $section) {
            foreach ($section->rows as $row) {
                $longest = max($longest, mb_strlen($column->display($row->cell($column->key))));
            }
        }

        return (int) min(55, max(10, ceil($longest * 1.15) + 3));
    }

    private function configurePrint(Worksheet $sheet, ReportDocument $document, int $headerRow): void
    {
        $sheet->getPageSetup()
            ->setOrientation($document->landscape ? PageSetup::ORIENTATION_LANDSCAPE : PageSetup::ORIENTATION_PORTRAIT)
            ->setPaperSize(PageSetup::PAPERSIZE_A4)
            ->setFitToWidth(1)
            ->setFitToHeight(0)
            ->setHorizontalCentered(true)
            ->setRowsToRepeatAtTopByStartAndEnd($headerRow, $headerRow);

        $sheet->getPageMargins()->setTop(0.5)->setBottom(0.6)->setLeft(0.4)->setRight(0.4)->setFooter(0.3);

        $footerTitle = str_replace('&', '&&', config('app.name').' · '.$document->title);
        $sheet->getHeaderFooter()->setOddFooter('&L&8'.$footerTitle.'&R&8Sayfa &P / &N');
    }

    private function numberFormat(int $decimals, ?string $suffix): string
    {
        $format = $decimals > 0 ? '#,##0.'.str_repeat('0', $decimals) : '#,##0';

        return $suffix !== null ? $format.' "'.$suffix.'"' : $format;
    }

    private function horizontalAlignment(ReportColumn $column): string
    {
        return match ($column->type) {
            ReportColumnType::Text => Alignment::HORIZONTAL_LEFT,
            ReportColumnType::Date => Alignment::HORIZONTAL_CENTER,
            default => Alignment::HORIZONTAL_RIGHT,
        };
    }

    private function sheetTitle(string $title): string
    {
        $clean = trim(str_replace(['*', ':', '/', '\\', '?', '[', ']'], ' ', $title));

        return mb_substr($clean !== '' ? $clean : 'Rapor', 0, 31);
    }
}
