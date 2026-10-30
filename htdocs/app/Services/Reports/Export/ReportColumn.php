<?php

namespace App\Services\Reports\Export;

use App\Enums\ReportColumnType;
use Illuminate\Support\Carbon;

final readonly class ReportColumn
{
    public function __construct(
        public string $key,
        public string $label,
        public ReportColumnType $type = ReportColumnType::Text,
        public int $decimals = 0,
        public ?string $suffix = null,
        public ?int $width = null,
    ) {}

    public static function text(string $key, string $label, ?int $width = null): self
    {
        return new self($key, $label, ReportColumnType::Text, width: $width);
    }

    public static function date(string $key, string $label): self
    {
        return new self($key, $label, ReportColumnType::Date, width: 12);
    }

    public static function number(string $key, string $label, int $decimals = 0, ?string $suffix = null, ?int $width = null): self
    {
        return new self($key, $label, ReportColumnType::Number, $decimals, $suffix, $width);
    }

    public static function money(string $key, string $label, int $decimals = 2): self
    {
        return new self($key, $label, ReportColumnType::Money, $decimals, 'TL', 16);
    }

    public static function balance(string $key, string $label): self
    {
        return new self($key, $label, ReportColumnType::Balance, 2, 'TL', 18);
    }

    public function decimalsFor(mixed $value): int
    {
        return $value instanceof ReportCell && $value->decimals !== null ? $value->decimals : $this->decimals;
    }

    public function suffixFor(mixed $value): ?string
    {
        return $value instanceof ReportCell && $value->suffix !== null ? $value->suffix : $this->suffix;
    }

    public function numericValue(mixed $value): float
    {
        return $value instanceof ReportCell ? (float) $value->value : (float) $value;
    }

    /**
     * Ekranda / PDF'te görünen hali: binlik ayraçlı, birimli; bakiyede B (borçlu) / A (alacaklı).
     */
    public function display(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return match ($this->type) {
            ReportColumnType::Text => (string) $value,
            ReportColumnType::Date => Carbon::parse($value)->format('d.m.Y'),
            ReportColumnType::Balance => $this->balanceDisplay($this->numericValue($value)),
            ReportColumnType::Number, ReportColumnType::Money => $this->numberDisplay($value),
        };
    }

    /**
     * CSV için birimsiz değer; bakiye işaretli yazılır.
     */
    public function plain(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return match ($this->type) {
            ReportColumnType::Text => (string) $value,
            ReportColumnType::Date => Carbon::parse($value)->format('d.m.Y'),
            default => number_format($this->numericValue($value), $this->decimalsFor($value), ',', '.'),
        };
    }

    private function numberDisplay(mixed $value): string
    {
        $text = number_format($this->numericValue($value), $this->decimalsFor($value), ',', '.');
        $suffix = $this->suffixFor($value);

        return $suffix !== null ? $text.' '.$suffix : $text;
    }

    private function balanceDisplay(float $amount): string
    {
        $amount = round($amount, 2);
        $text = number_format(abs($amount), 2, ',', '.').' TL';

        return match (true) {
            $amount > 0 => $text.' B',
            $amount < 0 => $text.' A',
            default => $text,
        };
    }
}
