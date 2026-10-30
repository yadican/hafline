<?php

namespace Tests\Feature;

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
use ReflectionProperty;
use Tests\TestCase;

class LoadTestReceivedInstrumentsTest extends TestCase
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

    public function test_settle_received_collects_endorses_and_bounces_matured_portfolio_papers(): void
    {
        $customer = Party::factory()->company()->withRole(PartyRole::Customer)->create();
        $supplier = Party::factory()->company()->withRole(PartyRole::Supplier)->create();
        (new ReflectionProperty(CatalogStage::class, 'partiesByRole'))->setValue($this->catalog, [
            PartyRole::Supplier->value => collect([$supplier]),
            PartyRole::Fuel->value => collect(),
            PartyRole::DumpOperator->value => collect(),
        ]);

        $entries = app(CariEntryService::class);
        $to = Carbon::parse('2026-06-30');

        for ($i = 1; $i <= 12; $i++) {
            $entries->create($this->catalog->withInstrumentDetails($this->catalog->withPaymentAccount([
                'kind' => 'customer',
                'direction' => 'credit',
                'party_id' => $customer->id,
                'payment_method' => $i % 3 === 0
                    ? PaymentMethod::PromissoryNote->value
                    : PaymentMethod::Cheque->value,
                'amount' => (string) (1000 * $i),
                'occurred_on' => '2026-02-01',
                'due_on' => $i <= 10 ? '2026-04-15' : '2026-08-01',
                'cheque_no' => sprintf('%s%06d', $i % 3 === 0 ? 'SN' : 'CK', $i),
                'description' => '43a sample '.$i,
            ])), null);
        }

        fake()->seed(43);

        $counts = (new InstrumentStage(new Progress(null), app(FinancialInstrumentService::class)))
            ->settleReceived($this->catalog, $to);

        $this->assertGreaterThan(0, $counts['collected']);
        $this->assertGreaterThan(0, $counts['endorsed']);
        $this->assertGreaterThanOrEqual(2, $counts['bounced']);
        $this->assertGreaterThan(0, $counts['portfolio']);

        $received = FinancialInstrument::query()
            ->where('direction', InstrumentDirection::Received)
            ->get();

        $this->assertCount(12, $received);
        $this->assertSame(
            $counts['collected'],
            $received->where('status', InstrumentStatus::Collected)->count(),
        );
        $this->assertSame(
            $counts['endorsed'],
            $received->where('status', InstrumentStatus::Endorsed)->count(),
        );
        $this->assertSame(
            $counts['bounced'],
            $received->where('status', InstrumentStatus::Bounced)->count(),
        );
        $this->assertSame(
            $counts['portfolio'],
            $received->where('status', InstrumentStatus::Portfolio)->count(),
        );

        $this->assertTrue(
            FinancialInstrumentMovement::query()->where('type', InstrumentMovementType::Collect)->exists(),
        );
        $this->assertTrue(
            FinancialInstrumentMovement::query()->where('type', InstrumentMovementType::Endorse)->exists(),
        );
        $this->assertTrue(
            FinancialInstrumentMovement::query()->where('type', InstrumentMovementType::Bounce)->exists(),
        );

        foreach ($received->where('status', InstrumentStatus::Collected) as $paper) {
            $this->assertTrue($paper->due_on->lte($to));
            $collect = $paper->movements->firstWhere('type', InstrumentMovementType::Collect);
            $this->assertNotNull($collect);
            $this->assertNotNull($collect->bank_id);
            $this->assertTrue($collect->occurred_on->gte($paper->issued_on));
            $this->assertTrue($collect->occurred_on->lte($to));
        }

        foreach ($received->where('status', InstrumentStatus::Endorsed) as $paper) {
            $endorse = $paper->movements->firstWhere('type', InstrumentMovementType::Endorse);
            $this->assertSame($supplier->id, $endorse->party_id);
        }

        $this->assertSame(
            2,
            $received->where('status', InstrumentStatus::Portfolio)
                ->filter(fn (FinancialInstrument $paper) => $paper->due_on->gt($to))
                ->count(),
        );

        $this->artisan('ledger:audit')
            ->expectsOutputToContain('Cari, belgeler ve raporlar birebir tutuyor.')
            ->assertSuccessful();
    }
}
