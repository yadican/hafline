<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Enums\InstrumentDirection;
use App\Enums\InstrumentMovementType;
use App\Enums\InstrumentStatus;
use App\Enums\PartyRole;
use App\Enums\PaymentMethod;
use App\Models\FinancialInstrument;
use App\Models\FinancialInstrumentMovement;
use App\Models\Party;
use App\Services\CariEntryService;
use App\Services\FinancialInstrumentService;
use Database\Seeders\CashCategorySeeder;
use Database\Seeders\LoadTest\CatalogStage;
use Database\Seeders\LoadTest\InstrumentStage;
use Database\Seeders\LoadTest\Support\Progress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use ReflectionMethod;
use Tests\TestCase;

class LoadTestGivenInstrumentsTest extends TestCase
{
    use RefreshDatabase;

    private CatalogStage $catalog;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CashCategorySeeder::class);
        $this->catalog = new CatalogStage(new Progress(null));
        (new ReflectionMethod(CatalogStage::class, 'seedBanks'))->invoke(
            $this->catalog,
            Carbon::parse('2026-01-01'),
        );
    }

    public function test_settle_given_pays_matured_issued_papers_from_bank_for_accounts(): void
    {
        $supplier = Party::factory()->company()->withRole(PartyRole::Supplier)->create();
        $entries = app(CariEntryService::class);
        $to = Carbon::parse('2026-06-30');

        for ($i = 1; $i <= 8; $i++) {
            $entries->create($this->catalog->withInstrumentDetails($this->catalog->withPaymentAccount([
                'kind' => 'supplier',
                'direction' => 'debit',
                'party_id' => $supplier->id,
                'payment_method' => $i % 2 === 0
                    ? PaymentMethod::PromissoryNote->value
                    : PaymentMethod::Cheque->value,
                'amount' => (string) (500 * $i),
                'occurred_on' => '2026-02-01',
                'due_on' => $i <= 6 ? '2026-04-15' : '2026-08-01',
                'cheque_no' => sprintf('%s%06d', $i % 2 === 0 ? 'SN' : 'CK', $i),
                'description' => '43b sample '.$i,
            ])), null);
        }

        fake()->seed(43);

        $counts = (new InstrumentStage(new Progress(null), app(FinancialInstrumentService::class)))
            ->settleGiven($this->catalog, $to);

        $this->assertSame(6, $counts['paid']);
        $this->assertSame(2, $counts['issued']);

        $given = FinancialInstrument::query()
            ->where('direction', InstrumentDirection::Given)
            ->get();

        $this->assertCount(8, $given);
        $this->assertSame(6, $given->where('status', InstrumentStatus::Paid)->count());
        $this->assertSame(2, $given->where('status', InstrumentStatus::Issued)->count());

        $payAccounts = $this->catalog->banksOf(AccountType::Bank)
            ->merge($this->catalog->banksOf(AccountType::Cash))
            ->pluck('id');

        foreach ($given->where('status', InstrumentStatus::Paid) as $paper) {
            $this->assertTrue($paper->due_on->lte($to));
            $pay = $paper->movements->firstWhere('type', InstrumentMovementType::Pay);
            $this->assertNotNull($pay);
            $this->assertTrue($payAccounts->contains($pay->bank_id));
            $this->assertTrue($pay->occurred_on->gte($paper->issued_on));
            $this->assertTrue($pay->occurred_on->lte($to));
        }

        foreach ($given->where('status', InstrumentStatus::Issued) as $paper) {
            $this->assertTrue($paper->due_on->gt($to));
            $this->assertFalse(
                $paper->movements->contains('type', InstrumentMovementType::Pay),
            );
        }

        $this->assertTrue(
            FinancialInstrumentMovement::query()->where('type', InstrumentMovementType::Pay)->exists(),
        );

        $this->artisan('ledger:audit')
            ->expectsOutputToContain('Cari, belgeler ve raporlar birebir tutuyor.')
            ->assertSuccessful();
    }
}
