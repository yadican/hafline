<?php

namespace App\Services\Reports\Export;

/**
 * Sayısal hücre; kolonun ondalık / birim ayarını tek hücre için ezer (ör. aynı kolonda sefer, litre, saat).
 */
final readonly class ReportCell
{
    public function __construct(
        public int|float $value,
        public ?int $decimals = null,
        public ?string $suffix = null,
    ) {}

    public static function money(int|float $value): self
    {
        return new self($value, 2, 'TL');
    }

    public function display(): string
    {
        $text = $this->plain();

        return $this->suffix !== null ? $text.' '.$this->suffix : $text;
    }

    public function plain(): string
    {
        return number_format($this->value, $this->decimals ?? 2, ',', '.');
    }
}
