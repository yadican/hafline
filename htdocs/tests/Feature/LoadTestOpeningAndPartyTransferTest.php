<?php

namespace Tests\Feature;

use App\Enums\PartyRole;
use App\Models\LedgerEntry;
use App\Models\OpeningBalance;
use App\Models\Party;
use App\Models\PartyTransfer;
use App\Repositories\PartyRepository;
use App\Services\CariEntryService;
use Database\Seeders\CashCategorySeeder;
use Database\Seeders\LoadTest\CariStage;
use Database\Seeders\LoadTest\CatalogStage;
use Database\Seeders\LoadTest\Support\Progress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use ReflectionMethod;
use ReflectionProperty;
use Tests\TestCase;

class LoadTestOpeningAndPartyTransferTest extends TestCase
{
    use RefreshDatabase;

    private CatalogStage $catalog;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CashCategorySeeder::class);
        $this->catalog = new CatalogStage(new Progress(null));
    }

    public function test_queue_openings_seeds_one_balance_per_party_on_period_start(): void
    {
        $this->seedParties([
            PartyRole::Customer->value => Party::factory()->company()->withRole(PartyRole::Customer)->count(3)->create(),
            PartyRole::Supplier->value => Party::factory()->company()->withRole(PartyRole::Supplier)->count(3)->create(),
            PartyRole::Fuel->value => Party::factory()->company()->withRole(PartyRole::Fuel)->count(2)->create(),
            PartyRole::DumpOperator->value => Party::factory()->company()->withRole(PartyRole::DumpOperator)->count(2)->create(),
            PartyRole::Market->value => Party::factory()->company()->withRole(PartyRole::Market)->count(2)->create(),
            PartyRole::VehicleOwner->value => Party::factory()->person()->withRole(PartyRole::VehicleOwner)->count(3)->create(),
        ]);

        $from = Carbon::parse('2026-01-01');
        $queue = (new ReflectionMethod(CariStage::class, 'queueOpenings'))->invoke(
            new CariStage(new Progress(null), app(CariEntryService::class)),
            $this->catalog,
            $from,
        );

        $this->assertNotEmpty($queue);
        $this->assertTrue(
            collect($queue)->every(fn (array $row) => $row['kind'] === 'opening'
                && $row['occurred_on'] === '2026-01-01'
                && in_array($row['direction'], ['debit', 'credit'], true)
                && (float) $row['amount'] >= 50
                && str_starts_with((string) $row['description'], 'Açılış bakiyesi')),
            'Açılış kuyruğu dönem başı opening satırları olmalı.',
        );
        $this->assertSame(
            collect($queue)->pluck('party_id')->unique()->count(),
            count($queue),
            'Her carinin en fazla bir açılışı olmalı.',
        );
        $this->assertTrue(
            collect($queue)->contains(fn (array $row) => $row['direction'] === 'debit'
                && str_contains((string) $row['description'], 'alıcı')),
            'Alıcılar borçlu (debit) açılmalı.',
        );
        $this->assertTrue(
            collect($queue)->contains(fn (array $row) => $row['direction'] === 'credit'
                && str_contains((string) $row['description'], 'satıcı')),
            'Satıcılar alacaklı (credit) açılmalı.',
        );
    }

    public function test_queue_party_transfers_offsets_borc_against_alacak(): void
    {
        $debtor = Party::factory()->company()->withRole(PartyRole::Customer)->create();
        $creditor = Party::factory()->company()->withRole(PartyRole::Supplier)->create();
        $entries = app(CariEntryService::class);

        $entries->create([
            'kind' => 'opening',
            'direction' => 'debit',
            'party_id' => $debtor->id,
            'amount' => '20000.00',
            'occurred_on' => '2026-01-01',
            'description' => 'Açılış bakiyesi · alıcı',
        ], null);

        $entries->create([
            'kind' => 'opening',
            'direction' => 'credit',
            'party_id' => $creditor->id,
            'amount' => '25000.00',
            'occurred_on' => '2026-01-01',
            'description' => 'Açılış bakiyesi · satıcı',
        ], null);

        $queue = (new ReflectionMethod(CariStage::class, 'queuePartyTransfers'))->invoke(
            new CariStage(new Progress(null), $entries),
            $this->catalog,
            Carbon::parse('2026-01-01'),
            Carbon::parse('2026-03-31'),
        );

        $this->assertNotEmpty($queue);
        $this->assertTrue(
            collect($queue)->contains(fn (array $row) => $row['kind'] === 'party_transfer'
                && $row['party_id'] === $creditor->id
                && $row['counter_party_id'] === $debtor->id
                && (float) $row['amount'] >= 500),
            'Virman alacaklıyı borçlandırıp borçluyu alacaklandırmalı.',
        );
        $this->assertTrue(
            collect($queue)->every(fn (array $row) => $row['occurred_on'] >= '2026-01-22'
                && $row['occurred_on'] <= '2026-03-31'),
            'Virmanlar dönem içinde olmalı.',
        );
    }

    public function test_seeded_openings_and_party_transfers_post_ledger_without_cash(): void
    {
        $customer = Party::factory()->company()->withRole(PartyRole::Customer)->create();
        $supplier = Party::factory()->company()->withRole(PartyRole::Supplier)->create();
        $entries = app(CariEntryService::class);
        $parties = app(PartyRepository::class);

        $entries->create([
            'kind' => 'opening',
            'direction' => 'debit',
            'party_id' => $customer->id,
            'amount' => '15000.00',
            'occurred_on' => '2026-01-01',
            'description' => 'Açılış bakiyesi · alıcı',
        ], null);

        $entries->create([
            'kind' => 'opening',
            'direction' => 'credit',
            'party_id' => $supplier->id,
            'amount' => '15000.00',
            'occurred_on' => '2026-01-01',
            'description' => 'Açılış bakiyesi · satıcı',
        ], null);

        $entries->create([
            'kind' => 'party_transfer',
            'party_id' => $supplier->id,
            'counter_party_id' => $customer->id,
            'amount' => '5000.00',
            'occurred_on' => '2026-02-15',
            'description' => 'Mahsup',
        ], null);

        $this->assertSame(2, OpeningBalance::query()->count());
        $this->assertSame(1, PartyTransfer::query()->count());
        $this->assertSame(0, LedgerEntry::query()->whereNotNull('payment_method')->count());
        $this->assertSame(4, LedgerEntry::query()->count());
        $this->assertSame(10000.0, $parties->totals($customer)['balance']);
        $this->assertSame(-10000.0, $parties->totals($supplier)['balance']);

        $this->artisan('ledger:audit')
            ->expectsOutputToContain('Cari, belgeler ve raporlar birebir tutuyor.')
            ->assertSuccessful();
    }

    /**
     * @param  array<string, Collection<int, Party>>  $byRole
     */
    private function seedParties(array $byRole): void
    {
        (new ReflectionProperty(CatalogStage::class, 'partiesByRole'))->setValue(
            $this->catalog,
            collect($byRole)->map(fn ($parties) => collect($parties))->all(),
        );
    }
}
