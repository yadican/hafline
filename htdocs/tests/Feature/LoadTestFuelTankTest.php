<?php

namespace Tests\Feature;

use App\Enums\FuelSourceType;
use App\Enums\PartyRole;
use App\Models\FuelReceipt;
use App\Models\FuelStation;
use App\Models\FuelTankCount;
use App\Models\FuelTankFill;
use App\Models\LedgerEntry;
use App\Models\Party;
use App\Models\Site;
use App\Models\Vehicle;
use App\Services\FuelTankService;
use App\Services\Ledger\FuelReceiptLedger;
use App\Services\Ledger\HaulLedger;
use App\Services\Ledger\Ledger;
use App\Services\Ledger\MachineWorkLedger;
use Database\Seeders\LoadTest\CatalogStage;
use Database\Seeders\LoadTest\OperationsStage;
use Database\Seeders\LoadTest\Support\Progress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use ReflectionMethod;
use ReflectionProperty;
use Tests\TestCase;

class LoadTestFuelTankTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_seeds_field_pumps_linked_to_sites(): void
    {
        $catalog = new CatalogStage(new Progress(null));

        $firms = Party::factory()->count(3)->company()->withRole(PartyRole::Fuel)->create();
        $sites = Site::factory()->count(8)->create();

        (new ReflectionProperty(CatalogStage::class, 'partiesByRole'))->setValue(
            $catalog,
            [PartyRole::Fuel->value => $firms],
        );
        (new ReflectionProperty(CatalogStage::class, 'sites'))->setValue($catalog, $sites);

        (new ReflectionMethod(CatalogStage::class, 'seedFuelStations'))->invoke($catalog);

        $tanks = $catalog->allTanks();

        $this->assertGreaterThanOrEqual(4, $tanks->count());
        $this->assertTrue(
            $tanks->every(fn (FuelStation $tank) => $tank->type === FuelSourceType::Tank
                && $tank->party_id === null
                && $tank->sites->isNotEmpty()
                && (float) $tank->unit_price > 0),
        );
    }

    public function test_operations_tank_fill_receipt_and_count_keep_stock_non_negative(): void
    {
        $supplier = Party::factory()->company()->withRole(PartyRole::Fuel)->create();
        $owner = Party::factory()->create();
        $site = Site::factory()->create();
        $tank = FuelStation::factory()->tank()->create(['unit_price' => 50]);
        $tank->sites()->attach($site->id);
        $vehicle = Vehicle::factory()->ownedBy($owner, '2026-01-01')->create();

        $catalog = new CatalogStage(new Progress(null));
        (new ReflectionProperty(CatalogStage::class, 'partiesByRole'))->setValue(
            $catalog,
            [PartyRole::Fuel->value => collect([$supplier])],
        );
        (new ReflectionProperty(CatalogStage::class, 'stations'))->setValue($catalog, collect([$tank]));

        $stage = new OperationsStage(
            new Progress(null),
            app(HaulLedger::class),
            app(MachineWorkLedger::class),
            app(FuelReceiptLedger::class),
            app(Ledger::class),
            app(FuelTankService::class),
        );

        (new ReflectionMethod(OperationsStage::class, 'seedTankOpeningStock'))->invoke(
            $stage,
            $catalog,
            Carbon::parse('2026-03-01'),
            null,
        );

        $this->assertSame(1, FuelTankFill::query()->count());
        $this->assertGreaterThan(0, (float) FuelTankFill::query()->sole()->quantity);
        $this->assertSame(1, LedgerEntry::query()->where('source_type', 'fuel_tank_fill')->count());

        $fillLitres = (float) FuelTankFill::query()->sole()->quantity;
        $draw = min(200, (int) $fillLitres);

        $created = (new ReflectionMethod(OperationsStage::class, 'createFuel'))->invoke(
            $stage,
            $vehicle,
            $tank,
            ['owner' => $owner->id, 'site' => $site->id, 'litres' => $draw, 'km' => 90_000, 'tickets' => 3],
            Carbon::parse('2026-03-10 08:20:00'),
            'Y202603000099',
            'Ali Veli',
            null,
            $catalog,
        );

        $this->assertTrue($created);

        $receipt = FuelReceipt::query()->sole();
        $this->assertNull($receipt->fuel_party_id);
        $this->assertSame('0.0000', $receipt->purchase_price);
        $this->assertSame('50.0000', $receipt->unit_price);
        $this->assertSame(1, LedgerEntry::query()->where('source_type', 'fuel_receipt')->count());
        $this->assertSame(0, LedgerEntry::query()->where('party_id', $supplier->id)->where('source_type', 'fuel_receipt')->count());

        (new ReflectionMethod(OperationsStage::class, 'seedTankCounts'))->invoke(
            $stage,
            $catalog,
            Carbon::parse('2026-03-01'),
            Carbon::parse('2026-03-31'),
            null,
        );

        $this->assertGreaterThanOrEqual(1, FuelTankCount::query()->count());

        $stock = app(FuelTankService::class)->stock($tank);
        $this->assertGreaterThanOrEqual(0, $stock['quantity']);

        $this->artisan('ledger:audit')
            ->expectsOutputToContain('Cari, belgeler ve raporlar birebir tutuyor.')
            ->assertSuccessful();
    }
}
