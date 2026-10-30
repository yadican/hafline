<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Models\Bank;
use Database\Seeders\LoadTest\CatalogStage;
use Database\Seeders\LoadTest\Support\Progress;
use Database\Seeders\LoadTest\Support\TurkishData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use ReflectionMethod;
use Tests\TestCase;

class LoadTestCatalogAccountsTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_seeds_cash_bank_pos_and_credit_card_accounts_with_opening_balances(): void
    {
        $catalog = new CatalogStage(new Progress(null));
        $openedOn = Carbon::parse('2026-01-01');

        (new ReflectionMethod(CatalogStage::class, 'seedBanks'))->invoke($catalog, $openedOn);

        foreach (AccountType::cases() as $type) {
            $accounts = $catalog->banksOf($type);
            $this->assertNotEmpty($accounts, $type->label().' hesabı seed edilmeli.');
            $this->assertTrue(
                $accounts->every(fn (Bank $bank) => $bank->opened_on?->toDateString() === '2026-01-01'),
                $type->label().' hesaplarının açılış tarihi dönem başı olmalı.',
            );
        }

        $this->assertSame(3, $catalog->banksOf(AccountType::Cash)->count());
        $this->assertSame(count(TurkishData::banks()), $catalog->banksOf(AccountType::Bank)->count());
        $this->assertSame(3, $catalog->banksOf(AccountType::Pos)->count());
        $this->assertSame(3, $catalog->banksOf(AccountType::CreditCard)->count());

        $this->assertTrue(
            $catalog->banksOf(AccountType::Cash)->every(fn (Bank $bank) => (float) $bank->opening_balance > 0),
            'Kasaların açılış bakiyesi pozitif olmalı.',
        );
        $this->assertTrue(
            $catalog->banksOf(AccountType::Bank)->every(fn (Bank $bank) => (float) $bank->opening_balance > 0),
            'Banka hesaplarının açılış bakiyesi pozitif olmalı.',
        );
        $this->assertTrue(
            $catalog->banksOf(AccountType::CreditCard)->every(fn (Bank $bank) => (float) $bank->opening_balance < 0),
            'Kredi kartı açılış bakiyesi borç (negatif) olmalı.',
        );

        $this->assertSame($catalog->allBanks()->count(), Bank::query()->count());
        $this->assertSame(
            $catalog->allBanks()->sum(fn (Bank $bank) => (float) $bank->opening_balance),
            (float) Bank::query()->sum('opening_balance'),
        );
    }
}
