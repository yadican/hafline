<?php

namespace Database\Seeders;

use App\Services\CariEntryService;
use App\Services\Ledger\FuelReceiptLedger;
use App\Services\Ledger\HaulLedger;
use App\Services\Ledger\MachineWorkLedger;
use Database\Seeders\LoadTest\CariStage;
use Database\Seeders\LoadTest\CatalogStage;
use Database\Seeders\LoadTest\OperationsStage;
use Database\Seeders\LoadTest\Support\Progress;
use Illuminate\Database\Seeder;

/**
 * 1 yıllık, kapasite bazlı yük / rapor test verisi.
 *
 * Filo: 200 kamyon + 50 makine. Sefer, yakıt ve makine saati günlük
 * kapasiteden türetilir; cari mahsup fiş ledger bakiyesine göre yazılır.
 *
 * Çalıştırma: php artisan migrate:fresh --seed
 * veya: php artisan db:seed --class=LoadTestSeeder
 */
class LoadTestSeeder extends Seeder
{
    public function run(
        HaulLedger $haulLedger,
        MachineWorkLedger $machineLedger,
        FuelReceiptLedger $fuelLedger,
        CariEntryService $cari,
    ): void {
        $progress = new Progress($this->command);
        $to = today();
        $from = $to->copy()->subMonths(OperationsStage::MONTHS - 1)->startOfMonth();
        $fleetSince = $from->copy()->subMonth();

        $progress->banner('Hafline Load Test Seeder (1 yıl)', $from, $to);
        $progress->note(sprintf(
            'Filo: %d kamyon + %d makine · %d ay · kapasite bazlı fiş + cari mahsup',
            CatalogStage::HAUL_VEHICLE_COUNT,
            CatalogStage::MACHINE_COUNT,
            OperationsStage::MONTHS,
        ));
        $progress->note('Beklenen: ~390k taşıma · ~65k yakıt · ~14k makine · cari fiş+mahsup');

        $this->callSilent([
            LocationSeeder::class,
            VehicleClassSeeder::class,
            CashCategorySeeder::class,
        ]);

        $catalog = (new CatalogStage($progress))->run($fleetSince);

        (new OperationsStage($progress, $haulLedger, $machineLedger, $fuelLedger))
            ->run($catalog, $from, $to);

        (new CariStage($progress, $cari))->run($catalog, $from, $to);

        $progress->stage('Özet');
        $progress->note('Şantiye: '.$catalog->allSites()->count());
        $progress->note('Döküm: '.$catalog->allDumpSites()->count());
        $progress->note('Güzergah: '.$catalog->allRoutes()->count());
        $progress->note('İstasyon: '.$catalog->allStations()->count());
        $progress->note('Araç: '.$catalog->allVehicles()->count()
            .' ('.$catalog->haulVehicles()->count().' kamyon + '.$catalog->machines()->count().' makine)');
        $progress->done('1 yıllık load test verisi hazır');
    }
}
