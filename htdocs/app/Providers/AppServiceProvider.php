<?php

namespace App\Providers;

use App\Models\CompanyTransaction;
use App\Models\DumpSitePayment;
use App\Models\FuelReceipt;
use App\Models\Haul;
use App\Models\MachineWork;
use App\Models\PartyPayment;
use App\Models\VehicleOwnership;
use App\Models\VehiclePayment;
use App\Models\VehicleReceivable;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // ledger_entries.source_type is varchar(40); keep class names out of it.
        Relation::morphMap([
            'haul' => Haul::class,
            'machine_work' => MachineWork::class,
            'fuel_receipt' => FuelReceipt::class,
            'company_transaction' => CompanyTransaction::class,
            'vehicle_payment' => VehiclePayment::class,
            'vehicle_receivable' => VehicleReceivable::class,
            'party_payment' => PartyPayment::class,
            'dump_site_payment' => DumpSitePayment::class,
            'vehicle_ownership' => VehicleOwnership::class,
        ]);
    }
}
