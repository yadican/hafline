<?php

namespace Tests\Feature;

use App\Enums\FuelSourceType;
use App\Enums\PartyRole;
use App\Enums\PaymentMethod;
use App\Models\CompanyTransaction;
use App\Models\FuelReceipt;
use App\Models\FuelStation;
use App\Models\LedgerEntry;
use App\Models\Party;
use App\Models\Site;
use App\Models\Vehicle;
use App\Services\CariEntryService;
use App\Services\FuelTankService;
use App\Services\Ledger\FuelReceiptLedger;
use App\Services\Ledger\HaulLedger;
use App\Services\Ledger\Ledger;
use App\Services\Ledger\MachineWorkLedger;
use Database\Seeders\CashCategorySeeder;
use Database\Seeders\LoadTest\CariStage;
use Database\Seeders\LoadTest\CatalogStage;
use Database\Seeders\LoadTest\OperationsStage;
use Database\Seeders\LoadTest\Support\Progress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use ReflectionMethod;
use ReflectionProperty;
use Tests\TestCase;

class LoadTestFuelPurchasePriceTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_seeds_station_purchase_price_below_sale_price(): void
    {
        $catalog = new CatalogStage(new Progress(null));

        $firms = Party::factory()->count(3)->company()->withRole(PartyRole::Fuel)->create();
        $sites = Site::factory()->count(4)->create();

        (new ReflectionProperty(CatalogStage::class, 'partiesByRole'))->setValue(
            $catalog,
            [PartyRole::Fuel->value => $firms],
        );
        (new ReflectionProperty(CatalogStage::class, 'sites'))->setValue($catalog, $sites);

        (new ReflectionMethod(CatalogStage::class, 'seedFuelStations'))->invoke($catalog);

        $stations = $catalog->allStations()->filter(fn (FuelStation $station) => ! $station->isTank());

        $this->assertNotEmpty($stations);
        $this->assertTrue(
            $stations->every(fn (FuelStation $station) => (float) $station->purchase_price > 0
                && (float) $station->purchase_price < (float) $station->unit_price),
            'Her istasyonun alış fiyatı satış fiyatının altında ve pozitif olmalı.',
        );
        $this->assertNotEmpty($catalog->allTanks());
        $this->assertTrue(
            $catalog->allTanks()->every(fn (FuelStation $tank) => $tank->type === FuelSourceType::Tank
                && $tank->party_id === null
                && (float) $tank->purchase_price === 0.0),
            'Saha pompalarının firması ve alış fiyatı olmamalı.',
        );
    }

    public function test_operations_fuel_receipt_copies_station_purchase_price_and_credits_the_firm(): void
    {
        $owner = Party::factory()->create();
        $station = FuelStation::factory()->create([
            'purchase_price' => 42.50,
            'unit_price' => 48.00,
        ]);
        $vehicle = Vehicle::factory()->ownedBy($owner, '2026-01-01')->create();
        $site = Site::factory()->create();
        $at = Carbon::parse('2026-03-10 08:15:00');

        $stage = new OperationsStage(
            new Progress(null),
            app(HaulLedger::class),
            app(MachineWorkLedger::class),
            app(FuelReceiptLedger::class),
            app(Ledger::class),
            app(FuelTankService::class),
        );

        $created = (new ReflectionMethod(OperationsStage::class, 'createFuel'))->invoke(
            $stage,
            $vehicle,
            $station,
            ['owner' => $owner->id, 'site' => $site->id, 'litres' => 200, 'km' => 120_000, 'tickets' => 4],
            $at,
            'Y202603000001',
            'Ali Veli',
            null,
            new CatalogStage(new Progress(null)),
        );

        $this->assertTrue($created);

        $receipt = FuelReceipt::query()->sole();
        $this->assertSame('48.0000', $receipt->unit_price);
        $this->assertSame('42.5000', $receipt->purchase_price);
        $this->assertSame($station->party_id, $receipt->fuel_party_id);

        $firmCredit = LedgerEntry::query()->where('party_id', $station->party_id)->sole();
        $this->assertSame('0.00', $firmCredit->debit);
        $this->assertSame($receipt->purchase_amount, $firmCredit->credit);
    }

    public function test_cari_stage_queues_fuel_payments_from_receipt_open_balance_without_invoices(): void
    {
        $this->seed(CashCategorySeeder::class);

        $firm = Party::factory()->company()->withRole(PartyRole::Fuel)->create();
        $station = FuelStation::factory()->create([
            'party_id' => $firm->id,
            'purchase_price' => 40,
            'unit_price' => 48,
        ]);
        $owner = Party::factory()->create();
        $vehicle = Vehicle::factory()->ownedBy($owner, '2026-01-01')->create();

        // Two weeks of receipts so the first Wednesday after +1 week has open alacak ≥ 5_000.
        foreach (['2026-03-02', '2026-03-03', '2026-03-04', '2026-03-05', '2026-03-06'] as $day) {
            FuelReceipt::factory()->create([
                'vehicle_id' => $vehicle->id,
                'party_id' => $owner->id,
                'fuel_station_id' => $station->id,
                'fuel_party_id' => $firm->id,
                'quantity' => 50,
                'unit_price' => 48,
                'purchase_price' => 40,
                'issued_on' => $day,
            ]);
        }

        $catalog = new CatalogStage(new Progress(null));
        (new ReflectionProperty(CatalogStage::class, 'partiesByRole'))->setValue(
            $catalog,
            [PartyRole::Fuel->value => collect([$firm])],
        );
        (new ReflectionMethod(CatalogStage::class, 'seedBanks'))->invoke(
            $catalog,
            Carbon::parse('2026-01-01'),
        );

        $queue = (new ReflectionMethod(CariStage::class, 'queueFuelSettlements'))->invoke(
            new CariStage(new Progress(null), app(CariEntryService::class)),
            $catalog,
            Carbon::parse('2026-03-01'),
            Carbon::parse('2026-03-31'),
        );

        $this->assertNotEmpty($queue);
        $this->assertTrue(
            collect($queue)->every(fn (array $row) => $row['kind'] === 'fuel'
                && $row['direction'] === 'debit'
                && $row['party_id'] === $firm->id
                && (float) $row['amount'] > 0
                && in_array($row['payment_method'], [
                    PaymentMethod::BankTransfer->value,
                    PaymentMethod::Cheque->value,
                ], true)),
            'Kuyruk yalnız akaryakıt firmasına borç (ödeme) satırları içermeli; fatura/alacak yok.',
        );

        $receiptTotal = (float) FuelReceipt::query()->where('fuel_party_id', $firm->id)->sum('purchase_amount');
        $paidTotal = collect($queue)->sum(fn (array $row) => (float) $row['amount']);
        $this->assertLessThanOrEqual($receiptTotal, $paidTotal + 50);
        $this->assertGreaterThan(0, $receiptTotal - $paidTotal);

        foreach ($queue as $payload) {
            app(CariEntryService::class)->create(
                $catalog->withInstrumentDetails(
                    $catalog->withPaymentAccount($catalog->withCashCategory($payload)),
                ),
                null,
            );
        }

        $credits = CompanyTransaction::query()
            ->where('role', PartyRole::Fuel->value)
            ->where('party_id', $firm->id)
            ->where('credit', '>', 0)
            ->count();
        $debits = CompanyTransaction::query()
            ->where('role', PartyRole::Fuel->value)
            ->where('party_id', $firm->id)
            ->where('debit', '>', 0)
            ->count();

        $this->assertSame(0, $credits, 'Çift alacak olmasın: firma carisinde fatura satırı yok.');
        $this->assertSame(count($queue), $debits);
    }
}
