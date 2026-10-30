<?php

namespace Tests\Feature;

use App\Enums\CashDirection;
use App\Enums\PartyRole;
use App\Enums\PaymentMethod;
use App\Models\CashCategory;
use App\Models\CashEntry;
use App\Models\CompanyTransaction;
use App\Models\Party;
use App\Models\PartyPayment;
use App\Services\CariEntryService;
use Database\Seeders\CashCategorySeeder;
use Database\Seeders\LoadTest\CatalogStage;
use Database\Seeders\LoadTest\Support\Progress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use ReflectionMethod;
use Tests\TestCase;

class LoadTestCashCategoriesTest extends TestCase
{
    use RefreshDatabase;

    private CatalogStage $catalog;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CashCategorySeeder::class);
        $this->catalog = new CatalogStage(new Progress(null));
    }

    public function test_category_for_picks_direction_matching_categories(): void
    {
        foreach ([CashDirection::Expense, CashDirection::Income] as $direction) {
            $pool = $this->catalog->categoriesOf($direction);
            $this->assertNotEmpty($pool, $direction->label().' kategorisi seed edilmeli.');
            $this->assertTrue(
                $pool->every(fn (CashCategory $category) => in_array($category->direction, [$direction, CashDirection::Both], true)),
                $direction->label().' havuzunda ters yönlü kategori olmamalı.',
            );

            $picked = $this->catalog->categoryFor($direction);
            $this->assertNotNull($picked);
            $this->assertContains($picked->direction, [$direction, CashDirection::Both]);
        }
    }

    public function test_category_direction_for_matches_cari_validation_rules(): void
    {
        $this->assertSame(CashDirection::Expense, $this->catalog->categoryDirectionFor('supplier', 'debit'));
        $this->assertSame(CashDirection::Income, $this->catalog->categoryDirectionFor('supplier', 'credit'));
        $this->assertSame(CashDirection::Expense, $this->catalog->categoryDirectionFor('site', 'debit'));
        $this->assertSame(CashDirection::Income, $this->catalog->categoryDirectionFor('site', 'credit'));
        $this->assertSame(CashDirection::Income, $this->catalog->categoryDirectionFor('cash', 'debit'));
        $this->assertSame(CashDirection::Expense, $this->catalog->categoryDirectionFor('cash', 'credit'));
    }

    public function test_with_cash_category_attaches_matching_category_and_keeps_existing(): void
    {
        $expenseId = $this->catalog->categoryFor(CashDirection::Expense)?->id;
        $expense = $this->catalog->withCashCategory(['kind' => 'supplier', 'direction' => 'debit']);
        $income = $this->catalog->withCashCategory(['kind' => 'cash', 'direction' => 'debit']);
        $kept = $this->catalog->withCashCategory([
            'kind' => 'cash',
            'direction' => 'credit',
            'cash_category_id' => $expenseId,
        ]);

        $this->assertContains(
            CashCategory::query()->findOrFail($expense['cash_category_id'])->direction,
            [CashDirection::Expense, CashDirection::Both],
        );
        $this->assertContains(
            CashCategory::query()->findOrFail($income['cash_category_id'])->direction,
            [CashDirection::Income, CashDirection::Both],
        );
        $this->assertSame($expenseId, $kept['cash_category_id']);
    }

    public function test_seeded_entries_use_categories_matching_their_direction(): void
    {
        (new ReflectionMethod(CatalogStage::class, 'seedBanks'))->invoke($this->catalog, Carbon::parse('2026-01-01'));

        $entries = app(CariEntryService::class);
        $supplier = Party::factory()->company()->withRole(PartyRole::Supplier)->create();
        $owner = Party::factory()->create();

        $samples = [
            ['kind' => 'supplier', 'direction' => 'debit', 'party_id' => $supplier->id, 'payment_method' => PaymentMethod::Cash->value],
            ['kind' => 'owner_payment', 'direction' => 'debit', 'party_id' => $owner->id, 'payment_method' => PaymentMethod::BankTransfer->value],
            ['kind' => 'cash', 'direction' => 'debit'],
            ['kind' => 'cash', 'direction' => 'credit'],
        ];

        foreach ($samples as $sample) {
            $entries->create($this->catalog->withPaymentAccount($this->catalog->withCashCategory([
                ...$sample,
                'amount' => '1000.00',
                'occurred_on' => '2026-02-15',
                'description' => '38d',
            ])), null);
        }

        CompanyTransaction::query()->whereNotNull('cash_category_id')->get()->each(function (CompanyTransaction $row) {
            $this->assertCategoryMatches($row->cash_category_id, 'supplier', (float) $row->debit > 0 ? 'debit' : 'credit');
        });

        PartyPayment::query()->whereNotNull('cash_category_id')->get()->each(function (PartyPayment $row) {
            $this->assertCategoryMatches($row->cash_category_id, 'owner_payment', 'debit');
        });

        CashEntry::query()->whereNotNull('cash_category_id')->get()->each(function (CashEntry $row) {
            $this->assertCategoryMatches($row->cash_category_id, 'cash', (float) $row->debit > 0 ? 'debit' : 'credit');
        });
    }

    private function assertCategoryMatches(?string $categoryId, string $kind, string $direction): void
    {
        $category = CashCategory::query()->findOrFail($categoryId);
        $expects = $this->catalog->categoryDirectionFor($kind, $direction);

        $this->assertContains(
            $category->direction,
            [$expects, CashDirection::Both],
            "{$kind}/{$direction} → {$category->name} ({$category->direction->value})",
        );
    }
}
