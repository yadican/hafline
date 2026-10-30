<?php

namespace Database\Seeders\LoadTest;

use App\Enums\PartyRole;
use App\Enums\Withholding;
use App\Exceptions\OperationRejectedException;
use App\Models\User;
use App\Services\ShipmentService;
use Database\Seeders\LoadTest\Support\Progress;
use Database\Seeders\LoadTest\Support\TurkishData;
use Illuminate\Support\Carbon;

/**
 * Transport (nakliye) trips: customer sale and carrier buy with VAT / withholding,
 * optional driver fee on the plate, and optional referrer (komisyoncu).
 */
final class ShipmentStage
{
    /** Trips seeded per month of the load-test window. */
    public const TRIPS_PER_MONTH = 14;

    /** @var list<string> */
    private const VAT_RATES = ['20', '20', '20', '20', '10', '0'];

    public function __construct(
        private Progress $progress,
        private ShipmentService $shipments,
    ) {}

    public function run(CatalogStage $catalog, Carbon $from, Carbon $to): void
    {
        $this->progress->stage('Nakliye seferleri');

        $customers = $catalog->partiesFor(PartyRole::Customer)->values();
        $commissioners = $catalog->partiesFor(PartyRole::Commission)->values();
        $vehicles = $catalog->haulVehicles();
        $sites = $catalog->allSites();
        $dumps = $catalog->allDumpSites();

        if ($customers->isEmpty() || $vehicles->isEmpty()) {
            $this->progress->done('müşteri veya araç yok — atlandı');

            return;
        }

        $days = $this->days($from, $to);
        $months = max(1, (int) $from->copy()->startOfMonth()->diffInMonths($to->copy()->startOfMonth()) + 1);
        $target = self::TRIPS_PER_MONTH * $months;
        $userId = User::query()->value('id');
        $created = 0;
        $waybill = 0;

        $this->progress->note(sprintf(
            'Hedef: %d sefer (%d ay) · %d müşteri · %d komisyoncu',
            $target,
            $months,
            $customers->count(),
            $commissioners->count(),
        ));

        for ($n = 0; $n < $target; $n++) {
            $day = fake()->randomElement($days);
            $vehicle = $vehicles->random();

            if (! $catalog->ownerOn($vehicle->id, $day)) {
                continue;
            }

            $customer = $customers->random();
            $withCommission = $commissioners->isNotEmpty() && fake()->boolean(72);
            $withDriver = fake()->boolean(85);
            $carrierPrice = fake()->randomElement([180, 220, 250, 280, 320, 380, 420, 480]);
            $salePrice = $carrierPrice + fake()->randomElement([40, 60, 80, 100, 120, 150, 180]);
            $netKg = fake()->randomElement([18_000, 22_000, 25_000, 28_000, 32_000, 35_000, 40_000, 45_000]);
            $loading = $dumps->isNotEmpty()
                ? $dumps->random()->name
                : fake()->randomElement(TurkishData::cityAreas()).' kum ocağı';
            $delivery = $sites->isNotEmpty()
                ? $sites->random()->name
                : fake()->randomElement(TurkishData::cityAreas()).' şantiyesi';

            $payload = [
                'plate' => $vehicle->plate,
                'shipped_on' => $day->toDateString(),
                'net_kg' => (string) $netKg,
                'loading_place' => $loading,
                'delivery_address' => $delivery,
                'customer_party_id' => $customer->id,
                'carrier_price' => (string) $carrierPrice,
                'carrier_vat_rate' => fake()->randomElement(self::VAT_RATES),
                'carrier_withholding_rate' => fake()->randomElement(Withholding::cases())->value,
                'sale_price' => (string) $salePrice,
                'vat_rate' => fake()->randomElement(self::VAT_RATES),
                'withholding_rate' => fake()->randomElement(Withholding::cases())->value,
                'waybill_no' => sprintf('NK-%d', ++$waybill),
                'scale_ticket_no' => fake()->boolean(60) ? fake()->numerify('KT######') : null,
                'driver_name' => $withDriver ? TurkishData::personName() : null,
                'driver_amount' => $withDriver ? (string) fake()->randomElement([800, 1000, 1200, 1500, 1800, 2000, 2500]) : '0',
            ];

            if ($withCommission) {
                $payload['commission_party_id'] = $commissioners->random()->id;
                $payload['commission_amount'] = (string) fake()->randomElement([500, 750, 1000, 1500, 2000, 3000, 5000]);
                $payload['commission_note'] = fake()->boolean(40) ? 'Referans hakedişi' : null;
            }

            try {
                $this->shipments->create($payload, $userId);
                $created++;
            } catch (OperationRejectedException) {
                continue;
            }

            $this->progress->batch($created, $target, 'nakliye');
        }

        $this->progress->done(number_format($created).' nakliye seferi');
    }

    /**
     * @return list<Carbon>
     */
    private function days(Carbon $from, Carbon $to): array
    {
        $days = [];
        $cursor = $from->copy()->startOfDay();

        while ($cursor->lte($to)) {
            $days[] = $cursor->copy();
            $cursor->addDay();
        }

        return $days;
    }
}
