<?php

namespace Database\Seeders;

use App\Enums\PartyRole;
use App\Repositories\VehicleAccountRepository;
use App\Services\CariEntryService;
use App\Services\FinancialInstrumentService;
use App\Services\FuelTankService;
use App\Services\InvoiceService;
use App\Services\Ledger\FuelReceiptLedger;
use App\Services\Ledger\HaulLedger;
use App\Services\Ledger\Ledger;
use App\Services\Ledger\MachineWorkLedger;
use App\Services\ShipmentService;
use App\Services\StockCountService;
use App\Services\StockService;
use App\Services\StockUsageService;
use App\Services\VehicleOwnershipService;
use Database\Seeders\LoadTest\CariStage;
use Database\Seeders\LoadTest\CatalogStage;
use Database\Seeders\LoadTest\InstrumentStage;
use Database\Seeders\LoadTest\InvoiceStage;
use Database\Seeders\LoadTest\OperationsStage;
use Database\Seeders\LoadTest\PayrollStage;
use Database\Seeders\LoadTest\ShipmentStage;
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
        Ledger $ledger,
        FuelTankService $fuelTanks,
        CariEntryService $cari,
        FinancialInstrumentService $instruments,
        ShipmentService $shipments,
        VehicleOwnershipService $ownerships,
        VehicleAccountRepository $accounts,
        InvoiceService $invoices,
        StockService $stock,
        StockUsageService $usages,
        StockCountService $counts,
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

        (new OperationsStage($progress, $haulLedger, $machineLedger, $fuelLedger, $ledger, $fuelTanks))
            ->run($catalog, $from, $to);

        (new ShipmentStage($progress, $shipments))->run($catalog, $from, $to);

        $invoiceStage = new InvoiceStage($progress, $invoices, $stock, $usages, $counts);
        (new CariStage($progress, $cari))->run($catalog, $from, $to, $invoiceStage);

        (new InstrumentStage($progress, $instruments))->run($catalog, $to);

        (new TransferStage($progress, $ownerships, $cari, $accounts))->run($to);

        (new PayrollStage($progress))->run($catalog, $from, $to);

        $progress->stage('Özet');
        $progress->note('Şantiye: '.$catalog->allSites()->count());
        $progress->note('Döküm: '.$catalog->allDumpSites()->count());
        $progress->note('Güzergah: '.$catalog->allRoutes()->count());
        $progress->note('İstasyon: '.$catalog->allStations()->count()
            .' ('.$catalog->allTanks()->count().' saha pompası)');
        $progress->note('Araç: '.$catalog->allVehicles()->count()
            .' ('.$catalog->haulVehicles()->count().' kamyon + '.$catalog->machines()->count().' makine)');
        $progress->note('Personel (Employee rolü): '.$catalog->partiesFor(PartyRole::Employee)->count());
        $progress->done(OperationsStage::MONTHS.' aylık load test verisi hazır');
    }
}
