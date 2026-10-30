<?php

namespace Tests\Feature;

use App\Enums\PartyRole;
use App\Enums\Withholding;
use App\Models\DumpSite;
use App\Models\LedgerEntry;
use App\Models\Party;
use App\Models\Shipment;
use App\Models\Site;
use App\Models\Vehicle;
use App\Services\ShipmentService;
use Database\Seeders\LoadTest\CatalogStage;
use Database\Seeders\LoadTest\ShipmentStage;
use Database\Seeders\LoadTest\Support\Progress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use ReflectionMethod;
use ReflectionProperty;
use Tests\TestCase;

class LoadTestShipmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_seeds_commission_parties_for_transport_referrals(): void
    {
        $catalog = new CatalogStage(new Progress(null));

        (new ReflectionMethod(CatalogStage::class, 'seedParties'))->invoke($catalog);

        $commissioners = $catalog->partiesFor(PartyRole::Commission);

        $this->assertGreaterThanOrEqual(18, $commissioners->count());
        $this->assertTrue(
            $commissioners->every(fn (Party $party) => $party->hasRole(PartyRole::Commission)),
            'Komisyoncu listesindeki her cari Commission rolüne sahip olmalı.',
        );
    }

    public function test_shipment_stage_posts_vat_withholding_driver_fee_and_commission_to_ledger(): void
    {
        $customer = Party::factory()->company()->withRole(PartyRole::Customer)->create();
        $carrier = Party::factory()->company()->withRole(PartyRole::VehicleOwner)->create();
        $referrer = Party::factory()->company()->withRole(PartyRole::Commission)->create();
        $vehicle = Vehicle::factory()->ownedBy($carrier, '2026-01-01')->create(['plate' => '34 NK 420']);
        $site = Site::factory()->create(['name' => 'Kartal şantiyesi']);
        $dump = DumpSite::factory()->create(['name' => 'Şile kum ocağı']);

        $catalog = new CatalogStage(new Progress(null));
        (new ReflectionProperty(CatalogStage::class, 'partiesByRole'))->setValue($catalog, [
            PartyRole::Customer->value => collect([$customer]),
            PartyRole::Commission->value => collect([$referrer]),
            PartyRole::VehicleOwner->value => collect([$carrier]),
        ]);
        (new ReflectionProperty(CatalogStage::class, 'haulVehicles'))->setValue($catalog, collect([$vehicle]));
        (new ReflectionProperty(CatalogStage::class, 'sites'))->setValue($catalog, collect([$site]));
        (new ReflectionProperty(CatalogStage::class, 'dumpSites'))->setValue($catalog, collect([$dump]));
        (new ReflectionProperty(CatalogStage::class, 'ownershipIndex'))->setValue($catalog, [
            $vehicle->id => [[
                'party_id' => $carrier->id,
                'started_on' => '2026-01-01',
                'ended_on' => null,
            ]],
        ]);

        fake()->seed(42);

        (new ShipmentStage(new Progress(null), app(ShipmentService::class)))
            ->run($catalog, Carbon::parse('2026-03-01'), Carbon::parse('2026-03-31'));

        $shipments = Shipment::query()->with('ledgerEntries')->get();

        $this->assertSame(ShipmentStage::TRIPS_PER_MONTH, $shipments->count());

        $withTax = $shipments->filter(
            fn (Shipment $s) => (float) $s->vat_rate > 0
                || (float) $s->carrier_vat_rate > 0
                || (float) $s->withholding_rate > 0
                || (float) $s->carrier_withholding_rate > 0,
        );
        $withDriver = $shipments->filter(fn (Shipment $s) => (float) $s->driver_amount > 0);
        $withCommission = $shipments->filter(fn (Shipment $s) => $s->commission_party_id !== null);

        $this->assertNotEmpty($withTax, 'En az bir seferde KDV veya tevkifat olmalı.');
        $this->assertNotEmpty($withDriver, 'En az bir seferde şoför ücreti olmalı.');
        $this->assertNotEmpty($withCommission, 'En az bir seferde komisyoncu olmalı.');
        $this->assertTrue(
            $withCommission->every(fn (Shipment $s) => (float) $s->commission_amount > 0
                && $s->commission_party_id === $referrer->id),
        );
        $this->assertTrue(
            $withDriver->every(fn (Shipment $s) => filled($s->driver_name)),
        );
        $this->assertTrue(
            $shipments->every(fn (Shipment $s) => in_array(
                Withholding::tryFrom(number_format((float) $s->withholding_rate, 2, '.', '')),
                Withholding::cases(),
                true,
            )),
        );

        $sample = $withCommission->first(
            fn (Shipment $s) => (float) $s->driver_amount > 0 && (float) $s->vat_rate > 0,
        ) ?? $withCommission->first();

        $this->assertNotNull($sample);

        $entries = LedgerEntry::query()->where('source_type', 'shipment')->where('source_id', $sample->id)->get();
        $this->assertSame(
            (float) $sample->customer_amount,
            (float) $entries->where('party_id', $customer->id)->sum('debit'),
        );
        $this->assertSame(
            (float) $sample->carrier_net_amount,
            (float) $entries->where('party_id', $carrier->id)->where('credit', '>', 0)
                ->filter(fn (LedgerEntry $e) => ! str_starts_with((string) $e->description, 'Şoför ücreti'))
                ->sum('credit'),
        );
        $this->assertSame(
            (float) $sample->driver_amount,
            (float) $entries->where('party_id', $carrier->id)
                ->filter(fn (LedgerEntry $e) => str_starts_with((string) $e->description, 'Şoför ücreti'))
                ->sum('credit'),
        );
        $this->assertSame(
            (float) $sample->commission_amount,
            (float) $entries->where('party_id', $referrer->id)->sum('credit'),
        );
        $this->assertTrue($referrer->fresh()->hasRole(PartyRole::Commission));
        $this->assertTrue($customer->fresh()->hasRole(PartyRole::Customer));
    }
}
