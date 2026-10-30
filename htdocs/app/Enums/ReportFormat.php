<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum ReportFormat: string
{
    use HasLabel;

    case Xlsx = 'xlsx';
    case Pdf = 'pdf';
    case Csv = 'csv';

    public function label(): string
    {
        return match ($this) {
            self::Xlsx => 'Excel',
            self::Pdf => 'PDF',
            self::Csv => 'CSV',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Xlsx => 'fa-solid fa-file-excel',
            self::Pdf => 'fa-solid fa-file-pdf',
            self::Csv => 'fa-solid fa-file-csv',
        };
    }
}
