<?php

namespace App\Enums;

enum ReportColumnType: string
{
    case Text = 'text';
    case Date = 'date';
    case Number = 'number';
    case Money = 'money';
    /** Cari bakiye: pozitif = borçlu (B), negatif = alacaklı (A). */
    case Balance = 'balance';

    public function isNumeric(): bool
    {
        return in_array($this, [self::Number, self::Money, self::Balance], true);
    }
}
