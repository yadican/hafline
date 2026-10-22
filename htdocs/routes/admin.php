<?php

use App\Http\Controllers\Admin\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Admin\BankController;
use App\Http\Controllers\Admin\CariEntryController;
use App\Http\Controllers\Admin\CariEntryLookupController;
use App\Http\Controllers\Admin\CashCategoryController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DumpSiteController;
use App\Http\Controllers\Admin\ExampleController;
use App\Http\Controllers\Admin\FuelReceiptController;
use App\Http\Controllers\Admin\FuelReceiptLookupController;
use App\Http\Controllers\Admin\FuelStationController;
use App\Http\Controllers\Admin\HaulController;
use App\Http\Controllers\Admin\HaulLookupController;
use App\Http\Controllers\Admin\HaulRouteController;
use App\Http\Controllers\Admin\LocationController;
use App\Http\Controllers\Admin\MachineWorkController;
use App\Http\Controllers\Admin\MachineWorkLookupController;
use App\Http\Controllers\Admin\PartyController;
use App\Http\Controllers\Admin\Reports\CashMovementReportController;
use App\Http\Controllers\Admin\Reports\CompanyBalanceReportController;
use App\Http\Controllers\Admin\Reports\CompanyLedgerReportController;
use App\Http\Controllers\Admin\Reports\DumpSitePaymentReportController;
use App\Http\Controllers\Admin\Reports\DumpSiteReportController;
use App\Http\Controllers\Admin\Reports\FuelStationMonthlyReportController;
use App\Http\Controllers\Admin\Reports\FuelStationReportController;
use App\Http\Controllers\Admin\Reports\MonthlyVoucherReportController;
use App\Http\Controllers\Admin\Reports\OwnerBalanceReportController;
use App\Http\Controllers\Admin\Reports\OwnerMonthlyReportController;
use App\Http\Controllers\Admin\Reports\OwnerPeriodicReportController;
use App\Http\Controllers\Admin\Reports\SiteCariReportController;
use App\Http\Controllers\Admin\Reports\SiteExitReportController;
use App\Http\Controllers\Admin\Reports\SiteExpenseReportController;
use App\Http\Controllers\Admin\Reports\TicketControlReportController;
use App\Http\Controllers\Admin\Reports\VehicleBalanceReportController;
use App\Http\Controllers\Admin\Reports\VehicleCostOwnerSummaryController;
use App\Http\Controllers\Admin\Reports\VehicleCostReportController;
use App\Http\Controllers\Admin\Reports\VehicleMonthlyReportController;
use App\Http\Controllers\Admin\Reports\VehiclePeriodicReportController;
use App\Http\Controllers\Admin\Reports\VehicleSiteMonthlyReportController;
use App\Http\Controllers\Admin\RouteVehiclePriceController;
use App\Http\Controllers\Admin\SiteController;
use App\Http\Controllers\Admin\VehicleClassController;
use App\Http\Controllers\Admin\VehicleController;
use App\Http\Controllers\Admin\VehicleOwnershipController;
use App\Http\Controllers\Admin\VehicleRateController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::resource('examples', ExampleController::class)->except(['show']);

    Route::resource('cash-categories', CashCategoryController::class)->except(['show']);
    Route::resource('banks', BankController::class)->except(['show']);

    Route::get('locations/cities/{city}/districts', [LocationController::class, 'districts'])->name('locations.districts');
    Route::get('locations/districts/{district}/neighborhoods', [LocationController::class, 'neighborhoods'])->name('locations.neighborhoods');

    Route::get('persons', [PartyController::class, 'index'])->name('persons.index')->defaults('type', 'person');
    Route::get('companies', [PartyController::class, 'index'])->name('companies.index')->defaults('type', 'company');
    Route::resource('parties', PartyController::class)->except(['index', 'show']);
    Route::get('cari-entries/lookups/plates', [CariEntryLookupController::class, 'plate'])->name('cari-entries.lookups.plate');
    Route::get('cari-entries/lookups/parties/{party}', [CariEntryLookupController::class, 'party'])->name('cari-entries.lookups.party');
    Route::get('cari-entries', [CariEntryController::class, 'index'])->name('cari-entries.index');
    Route::get('cari-entries/create', [CariEntryController::class, 'create'])->name('cari-entries.create');
    Route::post('cari-entries', [CariEntryController::class, 'store'])->name('cari-entries.store');
    Route::get('cari-entries/{kind}/{entry}/edit', [CariEntryController::class, 'edit'])->name('cari-entries.edit');
    Route::put('cari-entries/{kind}/{entry}', [CariEntryController::class, 'update'])->name('cari-entries.update');
    Route::delete('cari-entries/{kind}/{entry}', [CariEntryController::class, 'destroy'])->name('cari-entries.destroy');

    Route::resource('vehicles', VehicleController::class)->except(['show']);
    Route::resource('vehicles.ownerships', VehicleOwnershipController::class)->only(['store', 'destroy'])->scoped();
    Route::resource('vehicles.rates', VehicleRateController::class)->only(['store', 'destroy'])->scoped();
    Route::resource('vehicle-classes', VehicleClassController::class)->except(['show']);
    Route::resource('sites', SiteController::class)->except(['show']);
    Route::resource('dump-sites', DumpSiteController::class)->except(['show']);
    Route::resource('haul-routes', HaulRouteController::class)->except(['show']);
    Route::resource('haul-routes.vehicle-prices', RouteVehiclePriceController::class)->only(['store', 'destroy'])->scoped();
    Route::resource('fuel-stations', FuelStationController::class)->except(['show']);

    Route::get('hauls/lookups/dump-sites', [HaulLookupController::class, 'dumpSites'])->name('hauls.lookups.dump-sites');
    Route::get('hauls/lookups/quote', [HaulLookupController::class, 'quote'])->name('hauls.lookups.quote');
    Route::get('hauls/lookups/vehicles', [HaulLookupController::class, 'vehicles'])->name('hauls.lookups.vehicles');
    Route::resource('hauls', HaulController::class)->except(['show']);

    Route::get('machine-works/lookups/machines', [MachineWorkLookupController::class, 'machines'])->name('machine-works.lookups.machines');
    Route::get('machine-works/lookups/rate', [MachineWorkLookupController::class, 'rate'])->name('machine-works.lookups.rate');
    Route::get('machine-works/lookups/operator', [MachineWorkLookupController::class, 'operator'])->name('machine-works.lookups.operator');
    Route::resource('machine-works', MachineWorkController::class)->except(['show']);

    Route::get('fuel-receipts/lookups/vehicles', [FuelReceiptLookupController::class, 'vehicles'])->name('fuel-receipts.lookups.vehicles');
    Route::get('fuel-receipts/lookups/stations/{fuelStation}', [FuelReceiptLookupController::class, 'station'])->name('fuel-receipts.lookups.station');
    Route::resource('fuel-receipts', FuelReceiptController::class)->except(['show']);

    Route::get('reports/monthly-vouchers', [MonthlyVoucherReportController::class, 'index'])->name('reports.monthly-vouchers');
    Route::get('reports/monthly-vouchers/export', [MonthlyVoucherReportController::class, 'export'])->name('reports.monthly-vouchers.export');
    Route::get('reports/site-exits', [SiteExitReportController::class, 'index'])->name('reports.site-exits');
    Route::get('reports/site-exits/export', [SiteExitReportController::class, 'export'])->name('reports.site-exits.export');
    Route::get('reports/dump-sites', [DumpSiteReportController::class, 'index'])->name('reports.dump-sites');
    Route::get('reports/dump-sites/export', [DumpSiteReportController::class, 'export'])->name('reports.dump-sites.export');
    Route::get('reports/dump-site-payments', [DumpSitePaymentReportController::class, 'index'])->name('reports.dump-site-payments');
    Route::get('reports/dump-site-payments/export', [DumpSitePaymentReportController::class, 'export'])->name('reports.dump-site-payments.export');
    Route::get('reports/fuel-stations', [FuelStationReportController::class, 'index'])->name('reports.fuel-stations');
    Route::get('reports/fuel-stations/export', [FuelStationReportController::class, 'export'])->name('reports.fuel-stations.export');
    Route::get('reports/fuel-stations-monthly', [FuelStationMonthlyReportController::class, 'index'])->name('reports.fuel-stations-monthly');
    Route::get('reports/fuel-stations-monthly/export', [FuelStationMonthlyReportController::class, 'export'])->name('reports.fuel-stations-monthly.export');
    Route::get('reports/ticket-control', [TicketControlReportController::class, 'index'])->name('reports.ticket-control');
    Route::get('reports/ticket-control/export', [TicketControlReportController::class, 'export'])->name('reports.ticket-control.export');
    Route::get('reports/vehicle-monthly', [VehicleMonthlyReportController::class, 'index'])->name('reports.vehicle-monthly');
    Route::get('reports/vehicle-monthly/export', [VehicleMonthlyReportController::class, 'export'])->name('reports.vehicle-monthly.export');
    Route::get('reports/vehicle-periodic', [VehiclePeriodicReportController::class, 'index'])->name('reports.vehicle-periodic');
    Route::get('reports/vehicle-periodic/export', [VehiclePeriodicReportController::class, 'export'])->name('reports.vehicle-periodic.export');
    Route::get('reports/vehicle-site-monthly', [VehicleSiteMonthlyReportController::class, 'index'])->name('reports.vehicle-site-monthly');
    Route::get('reports/vehicle-site-monthly/export', [VehicleSiteMonthlyReportController::class, 'export'])->name('reports.vehicle-site-monthly.export');
    Route::get('reports/vehicle-balance', [VehicleBalanceReportController::class, 'index'])->name('reports.vehicle-balance');
    Route::get('reports/vehicle-balance/export', [VehicleBalanceReportController::class, 'export'])->name('reports.vehicle-balance.export');
    Route::get('reports/owner-monthly', [OwnerMonthlyReportController::class, 'index'])->name('reports.owner-monthly');
    Route::get('reports/owner-monthly/export', [OwnerMonthlyReportController::class, 'export'])->name('reports.owner-monthly.export');
    Route::get('reports/owner-periodic', [OwnerPeriodicReportController::class, 'index'])->name('reports.owner-periodic');
    Route::get('reports/owner-periodic/export', [OwnerPeriodicReportController::class, 'export'])->name('reports.owner-periodic.export');
    Route::get('reports/owner-balance', [OwnerBalanceReportController::class, 'index'])->name('reports.owner-balance');
    Route::get('reports/owner-balance/export', [OwnerBalanceReportController::class, 'export'])->name('reports.owner-balance.export');
    Route::get('reports/vehicle-cost', [VehicleCostReportController::class, 'index'])->name('reports.vehicle-cost');
    Route::get('reports/vehicle-cost/export', [VehicleCostReportController::class, 'export'])->name('reports.vehicle-cost.export');
    Route::get('reports/vehicle-cost/owners', [VehicleCostOwnerSummaryController::class, 'index'])->name('reports.vehicle-cost.owners');
    Route::get('reports/vehicle-cost/owners/export', [VehicleCostOwnerSummaryController::class, 'export'])->name('reports.vehicle-cost.owners.export');
    Route::get('reports/company-balance', [CompanyBalanceReportController::class, 'index'])->name('reports.company-balance');
    Route::get('reports/company-balance/export', [CompanyBalanceReportController::class, 'export'])->name('reports.company-balance.export');
    Route::get('reports/company-ledger', [CompanyLedgerReportController::class, 'index'])->name('reports.company-ledger');
    Route::get('reports/company-ledger/export', [CompanyLedgerReportController::class, 'export'])->name('reports.company-ledger.export');
    Route::get('reports/site-cari', [SiteCariReportController::class, 'index'])->name('reports.site-cari');
    Route::get('reports/site-cari/export', [SiteCariReportController::class, 'export'])->name('reports.site-cari.export');
    Route::get('reports/site-expense', [SiteExpenseReportController::class, 'index'])->name('reports.site-expense');
    Route::get('reports/site-expense/export', [SiteExpenseReportController::class, 'export'])->name('reports.site-expense.export');
    Route::get('reports/cash-movements', [CashMovementReportController::class, 'index'])->name('reports.cash-movements');
    Route::get('reports/cash-movements/export', [CashMovementReportController::class, 'export'])->name('reports.cash-movements.export');
});
