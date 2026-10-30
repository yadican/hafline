<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Enums\PaymentMethod;
use App\Models\AccountTransfer;
use App\Models\CashEntry;
use App\Models\LedgerEntry;
use App\Models\Site;
use App\Models\SiteTransaction;
use App\Repositories\BankRepository;
use App\Services\CariEntryService;
use Database\Seeders\CashCategorySeeder;
use Database\Seeders\LoadTest\CariStage;
use Database\Seeders\LoadTest\CatalogStage;
use Database\Seeders\LoadTest\Support\Progress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use ReflectionMethod;
use Tests\TestCase;

class LoadTestAccountTransfersTest extends TestCase
{
    use RefreshDatabase;

    private CatalogStage $catalog;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CashCategorySeeder::class);
        $this->catalog = new CatalogStage(new Progress(null));
        (new ReflectionMethod(CatalogStage::class, 'seedBanks'))->invoke($this->catalog, Carbon::parse('2026-01-01'));
    }

    public function test_cash_drawer_queues_bank_to_cash_and_cash_to_bank_transfers(): void
    {
        SiteTransaction::query()->create([
            'site_id' => Site::factory()->create()->id,
            'payment_method' => PaymentMethod::Cash,
            'debit' => 0,
            'credit' => 500_000,
            'occurred_on' => '2026-01-05',
            'description' => 'Büyük nakit tahsilat',
        ]);

        $stage = new CariStage(new Progress(null), app(CariEntryService::class));
        $queue = (new ReflectionMethod(CariStage::class, 'queueCashDrawer'))->invoke(
            $stage,
            $this->catalog,
            Carbon::parse('2026-01-01'),
            Carbon::parse('2026-01-10'),
        );

        $transfers = collect($queue)->where('kind', 'account_transfer')->values();
        $bankIds = $this->catalog->banksOf(AccountType::Bank)->pluck('id');
        $cashIds = $this->catalog->banksOf(AccountType::Cash)->pluck('id');

        $this->assertTrue(
            $transfers->contains(fn (array $row) => $bankIds->contains($row['bank_id'])
                && $cashIds->contains($row['counter_bank_id'])
                && $row['description'] === 'Bankadan kasaya çekim'),
            'Kasa düşükken banka → kasa virmanı kuyruğa girmeli.',
        );
        $this->assertTrue(
            $transfers->contains(fn (array $row) => $cashIds->contains($row['bank_id'])
                && $bankIds->contains($row['counter_bank_id'])
                && $row['description'] === 'Kasadan bankaya yatırma'),
            'Kasa yüksekken kasa → banka virmanı kuyruğa girmeli.',
        );
        $this->assertTrue(
            collect($queue)->where('kind', 'cash')->every(
                fn (array $row) => ! in_array($row['description'] ?? '', ['Bankadan çekilen', 'Bankaya yatan'], true),
            ),
            'Eski sahte "Bankadan çekilen / Bankaya yatan" kasa satırı olmamalı.',
        );
    }

    public function test_seeded_account_transfers_move_money_between_bank_and_cash(): void
    {
        $bank = $this->catalog->banksOf(AccountType::Bank)->firstOrFail();
        $cash = $this->catalog->banksOf(AccountType::Cash)->firstOrFail();
        $entries = app(CariEntryService::class);
        $before = app(BankRepository::class)->balances([$bank->id, $cash->id]);

        $entries->create([
            'kind' => 'account_transfer',
            'bank_id' => $bank->id,
            'counter_bank_id' => $cash->id,
            'amount' => '25000.00',
            'occurred_on' => '2026-02-10',
            'description' => 'Bankadan kasaya çekim',
        ], null);

        $entries->create([
            'kind' => 'account_transfer',
            'bank_id' => $cash->id,
            'counter_bank_id' => $bank->id,
            'amount' => '8000.00',
            'occurred_on' => '2026-02-12',
            'description' => 'Kasadan bankaya yatırma',
        ], null);

        $after = app(BankRepository::class)->balances([$bank->id, $cash->id]);

        $this->assertSame(2, AccountTransfer::query()->count());
        $this->assertSame(0, LedgerEntry::query()->count());
        $this->assertSame(0, CashEntry::query()->whereIn('description', ['Bankadan çekilen', 'Bankaya yatan'])->count());
        $this->assertSame(round($before[$bank->id] - 25000 + 8000, 2), $after[$bank->id]);
        $this->assertSame(round($before[$cash->id] + 25000 - 8000, 2), $after[$cash->id]);
        $this->assertTrue(
            AccountTransfer::query()->where('from_bank_id', $bank->id)->where('to_bank_id', $cash->id)->exists(),
        );
        $this->assertTrue(
            AccountTransfer::query()->where('from_bank_id', $cash->id)->where('to_bank_id', $bank->id)->exists(),
        );
    }
}
