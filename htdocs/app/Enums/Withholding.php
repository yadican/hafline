<?php

namespace App\Enums;

/**
 * VAT withholding (tevkifat) ratios: the share of the VAT the customer keeps and pays to the tax office.
 */
enum Withholding: string
{
    case None = '0.00';
    case TwoTenths = '0.20';
    case FourTenths = '0.40';
    case FiveTenths = '0.50';
    case SevenTenths = '0.70';
    case NineTenths = '0.90';

    public function label(): string
    {
        return match ($this) {
            self::None => 'Tevkifat yok',
            self::TwoTenths => '2/10',
            self::FourTenths => '4/10',
            self::FiveTenths => '5/10',
            self::SevenTenths => '7/10',
            self::NineTenths => '9/10',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_combine(
            array_map(fn (self $case) => $case->value, self::cases()),
            array_map(fn (self $case) => $case->label(), self::cases()),
        );
    }
}
