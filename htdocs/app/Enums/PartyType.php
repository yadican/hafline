<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum PartyType: string
{
    use HasLabel;

    case Person = 'person';
    case Company = 'company';

    public function label(): string
    {
        return match ($this) {
            self::Person => 'Kişi',
            self::Company => 'Şirket',
        };
    }

    public function pluralLabel(): string
    {
        return match ($this) {
            self::Person => 'Kişiler',
            self::Company => 'Şirketler',
        };
    }

    public function indexRoute(): string
    {
        return match ($this) {
            self::Person => 'admin.persons.index',
            self::Company => 'admin.companies.index',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Person => 'fa-solid fa-user',
            self::Company => 'fa-solid fa-building',
        };
    }
}
