<?php

namespace Tests\Feature;

use App\Enums\PartyRole;
use App\Enums\PaymentMethod;
use App\Models\CompanyTransaction;
use App\Models\Party;
use App\Models\PartyPayment;
use App\Models\Vehicle;
use App\Models\VehiclePayment;
use App\Services\CariEntryService;
use App\Services\ShipmentService;
use Database\Seeders\CashCategorySeeder;
use Database\Seeders\LoadTest\CariStage;
use Database\Seeders\LoadTest\CatalogStage;
use Database\Seeders\LoadTest\Support\Progress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use ReflectionMethod;
use ReflectionProperty;
use Tests\TestCase;

class LoadTestShipmentPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_cari_stage_queues_customer_collections_carrier_and_commission_payments_from_trip_balances(): void
    {
        $this->seed(CashCategorySeeder::class);

        $customer = Party::factory()->company()->withRole(PartyRole::Customer)->create();
        $carrier = Party::factory()->company()->withRole(PartyRole::VehicleOwner)->create();
        $referrer = Party::factory()->company()->withRole(PartyRole::Commission)->create();
        $vehicle = Vehicle::factory()->ownedBy($carrier, '2026-01-01')->create(['plate' => '34 NK 421']);

        $shipments = app(ShipmentService::class);
        foreach (['2026-03-02', '2026-03-03', '2026-03-04', '2026-03-05', '2026-03-06'] as $day) {
            $shipments->create([
                'plate' => $vehicle->plate,
                'shipped_on' => $day,
                'net_kg' => '25000',
                'loading_place' => 'Şile kum ocağı',
                'delivery_address' => 'Kartal şantiyesi',
                'customer_party_id' => $customer->id,
                'carrier_price' => '400',
                'carrier_vat_rate' => '0',
                'carrier_withholding_rate' => '0',
                'sale_price' => '550',
                'vat_rate' => '0',
                'withholding_rate' => '0',
                'waybill_no' => 'NK-'.$day,
                'driver_name' => 'Ali Veli',
                'driver_amount' => '1000',
                'commission_party_id' => $referrer->id,
                'commission_amount' => '2000',
            ], null);
        }

        $catalog = new CatalogStage(new Progress(null));
        (new ReflectionProperty(CatalogStage::class, 'partiesByRole'))->setValue($catalog, [
            PartyRole::Customer->value => collect([$customer]),
            PartyRole::Commission->value => collect([$referrer]),
            PartyRole::VehicleOwner->value => collect([$carrier]),
        ]);
        (new ReflectionProperty(CatalogStage::class, 'ownershipIndex'))->setValue($catalog, [
            $vehicle->id => [[
                'party_id' => $carrier->id,
                'started_on' => '2026-01-01',
                'ended_on' => null,
            ]],
        ]);
        (new ReflectionMethod(CatalogStage::class, 'seedBanks'))->invoke(
            $catalog,
            Carbon::parse('2026-01-01'),
        );

        fake()->seed(7);

        $queue = (new ReflectionMethod(CariStage::class, 'queueShipmentSettlements'))->invoke(
            new CariStage(new Progress(null), app(CariEntryService::class)),
            $catalog,
            Carbon::parse('2026-03-01'),
            Carbon::parse('2026-03-31'),
        );

        $collections = array_values(array_filter(
            $queue,
            fn (array $row) => ($row['kind'] ?? null) === 'customer' && ($row['direction'] ?? null) === 'credit',
        ));
        $carrierPays = array_values(array_filter(
            $queue,
            fn (array $row) => ($row['kind'] ?? null) === 'vehicle_payment',
        ));
        $commissionPays = array_values(array_filter(
            $queue,
            fn (array $row) => ($row['kind'] ?? null) === 'owner_payment',
        ));

        $this->assertNotEmpty($collections, 'Müşteri tahsilatı kuyrukta olmalı.');
        $this->assertTrue(
            collect($collections)->every(fn (array $row) => $row['party_id'] === $customer->id
                && ($row['description'] ?? null) === 'Nakliye tahsilatı'
                && (float) $row['amount'] > 0
                && filled($row['payment_method'] ?? null)),
        );

        $this->assertNotEmpty($carrierPays, 'Nakliyeci ödemesi kuyrukta olmalı.');
        $this->assertTrue(
            collect($carrierPays)->every(fn (array $row) => $row['plate'] === $vehicle->plate
                && ($row['description'] ?? null) === 'Nakliyeci ödemesi'
                && (float) $row['amount'] > 0),
        );

        $this->assertNotEmpty($commissionPays, 'Komisyoncu ödemesi kuyrukta olmalı.');
        $this->assertTrue(
            collect($commissionPays)->every(fn (array $row) => $row['party_id'] === $referrer->id
                && ($row['direction'] ?? null) === 'debit'
                && ($row['description'] ?? null) === 'Komisyon ödemesi'
                && (float) $row['amount'] > 0),
        );

        $customerOpen = 25_000 / 1000 * 550 * 5;
        $carrierOpen = (25_000 / 1000 * 400 + 1000) * 5;
        $commissionOpen = 2000 * 5;

        $this->assertLessThanOrEqual($customerOpen, collect($collections)->sum(fn (array $r) => (float) $r['amount']) + 50);
        $this->assertLessThanOrEqual($carrierOpen, collect($carrierPays)->sum(fn (array $r) => (float) $r['amount']) + 50);
        $this->assertLessThanOrEqual($commissionOpen, collect($commissionPays)->sum(fn (array $r) => (float) $r['amount']) + 50);
        $this->assertGreaterThan(0, $customerOpen - collect($collections)->sum(fn (array $r) => (float) $r['amount']));
        $this->assertGreaterThan(0, $carrierOpen - collect($carrierPays)->sum(fn (array $r) => (float) $r['amount']));
        $this->assertGreaterThan(0, $commissionOpen - collect($commissionPays)->sum(fn (array $r) => (float) $r['amount']));

        foreach ($queue as $payload) {
            app(CariEntryService::class)->create(
                $catalog->withInstrumentDetails(
                    $catalog->withPaymentAccount($catalog->withCashCategory($payload)),
                ),
                null,
            );
        }

        $this->assertSame(
            count($collections),
            CompanyTransaction::query()
                ->where('role', 'customer')
                ->where('party_id', $customer->id)
                ->where('credit', '>', 0)
                ->count(),
        );
        $this->assertSame(
            count($carrierPays),
            VehiclePayment::query()->where('vehicle_id', $vehicle->id)->where('description', 'Nakliyeci ödemesi')->count(),
        );
        $this->assertSame(
            count($commissionPays),
            PartyPayment::query()->where('party_id', $referrer->id)->where('description', 'Komisyon ödemesi')->count(),
        );

        $cashCollections = collect($collections)->filter(
            fn (array $row) => ($row['payment_method'] ?? null) === PaymentMethod::Cash->value,
        );
        foreach ($cashCollections as $row) {
            $posted = CompanyTransaction::query()
                ->where('role', 'customer')
                ->where('party_id', $customer->id)
                ->where('credit', $row['amount'])
                ->where('occurred_on', $row['occurred_on'])
                ->first();
            $this->assertNotNull($posted?->bank_id, 'Nakit tahsilat kasa hesabı seçmeli.');
            $this->assertNotNull($posted?->cash_category_id, 'Nakit tahsilat gelir kategorisi seçmeli.');
        }
    }
}
