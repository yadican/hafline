<?php

namespace App\Providers;

use App\Models\CompanyTransaction;
use App\Models\DumpSitePayment;
use App\Models\EmployeeBonus;
use App\Models\EmployeePayment;
use App\Models\EmployeeSalary;
use App\Models\FinancialInstrumentMovement;
use App\Models\FuelReceipt;
use App\Models\FuelTankFill;
use App\Models\Haul;
use App\Models\MachineWork;
use App\Models\OpeningBalance;
use App\Models\PartyPayment;
use App\Models\PartyTransfer;
use App\Models\Shipment;
use App\Models\SiteBill;
use App\Models\SiteTransaction;
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
            'fuel_tank_fill' => FuelTankFill::class,
            'company_transaction' => CompanyTransaction::class,
            'vehicle_payment' => VehiclePayment::class,
            'vehicle_receivable' => VehicleReceivable::class,
            'party_payment' => PartyPayment::class,
            'dump_site_payment' => DumpSitePayment::class,
            'vehicle_ownership' => VehicleOwnership::class,
            'employee_salary' => EmployeeSalary::class,
            'employee_bonus' => EmployeeBonus::class,
            'employee_payment' => EmployeePayment::class,
            'opening_balance' => OpeningBalance::class,
            'party_transfer' => PartyTransfer::class,
            'site_bill' => SiteBill::class,
            'site_transaction' => SiteTransaction::class,
            'shipment' => Shipment::class,
            'financial_instrument_movement' => FinancialInstrumentMovement::class,
        ]);
    }
}
