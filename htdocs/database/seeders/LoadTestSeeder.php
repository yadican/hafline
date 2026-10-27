<?php

namespace Database\Seeders;

use App\Enums\PartyRole;
use App\Repositories\VehicleAccountRepository;
use App\Services\CariEntryService;
use App\Services\Ledger\FuelReceiptLedger;
use App\Services\Ledger\HaulLedger;
use App\Services\Ledger\MachineWorkLedger;
use App\Services\VehicleOwnershipService;
use Database\Seeders\LoadTest\CariStage;
use Database\Seeders\LoadTest\CatalogStage;
use Database\Seeders\LoadTest\OperationsStage;
use Database\Seeders\LoadTest\PayrollStage;
use Database\Seeders\LoadTest\Support\Progress;
use Database\Seeders\LoadTest\TransferStage;
use Illuminate\Database\Seeder;

/**
 * 3 aylık, kapasite bazlı yük / rapor test verisi.
 *
 * Filo: 200 kamyon + 50 makine, 40 şantiye, 15 döküm yeri. Kamyon bir gün
 * boyunca tek güzergahta çalışır; sefer sayısı güzergahın tur süresinden,
 * yakıt yapılan işten, cari hareketler o tarihteki açık bakiyeden türetilir.
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
        VehicleOwnershipService $ownerships,
        VehicleAccountRepository $accounts,
    ): void {
        $progress = new Progress($this->command);
        $to = today();
        $from = $to->copy()->subMonths(OperationsStage::MONTHS - 1)->startOfMonth();
        $fleetSince = $from->copy()->subMonth();

        $progress->banner(sprintf('Hafline Load Test Seeder (%d ay)', OperationsStage::MONTHS), $from, $to);
        $progress->note(sprintf(
            'Filo: %d kamyon + %d makine · %d şantiye · %d döküm · %d ay',
            CatalogStage::HAUL_VEHICLE_COUNT,
            CatalogStage::MACHINE_COUNT,
            CatalogStage::SITE_COUNT,
            CatalogStage::DUMP_COUNT,
            OperationsStage::MONTHS,
        ));

        $this->callSilent([
            LocationSeeder::class,
            VehicleClassSeeder::class,
            CashCategorySeeder::class,
        ]);

        $catalog = (new CatalogStage($progress))->run($fleetSince, $from, $to);

        (new OperationsStage($progress, $haulLedger, $machineLedger, $fuelLedger))
            ->run($catalog, $from, $to);

        (new CariStage($progress, $cari))->run($catalog, $from, $to);

        (new TransferStage($progress, $ownerships, $cari, $accounts))->run($to);

        (new PayrollStage($progress))->run($catalog, $from, $to);

        $progress->stage('Özet');
        $progress->note('Şantiye: '.$catalog->allSites()->count());
        $progress->note('Döküm: '.$catalog->allDumpSites()->count());
        $progress->note('Güzergah: '.$catalog->allRoutes()->count());
        $progress->note('İstasyon: '.$catalog->allStations()->count());
        $progress->note('Araç: '.$catalog->allVehicles()->count()
            .' ('.$catalog->haulVehicles()->count().' kamyon + '.$catalog->machines()->count().' makine)');
        $progress->note('Personel (Employee rolü): '.$catalog->partiesFor(PartyRole::Employee)->count());
        $progress->done(OperationsStage::MONTHS.' aylık load test verisi hazır');
    }
}
