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
use App\Http\Controllers\Admin\Reports\DumpSiteReportController;
use App\Http\Controllers\Admin\Reports\FuelStationReportController;
use App\Http\Controllers\Admin\Reports\OwnerMonthlyReportController;
use App\Http\Controllers\Admin\Reports\SiteExitReportController;
use App\Http\Controllers\Admin\Reports\VehicleMonthlyReportController;
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

    Route::get('reports/site-exits', [SiteExitReportController::class, 'index'])->name('reports.site-exits');
    Route::get('reports/site-exits/export', [SiteExitReportController::class, 'export'])->name('reports.site-exits.export');
    Route::get('reports/dump-sites', [DumpSiteReportController::class, 'index'])->name('reports.dump-sites');
    Route::get('reports/dump-sites/export', [DumpSiteReportController::class, 'export'])->name('reports.dump-sites.export');
    Route::get('reports/fuel-stations', [FuelStationReportController::class, 'index'])->name('reports.fuel-stations');
    Route::get('reports/fuel-stations/export', [FuelStationReportController::class, 'export'])->name('reports.fuel-stations.export');
    Route::get('reports/vehicle-monthly', [VehicleMonthlyReportController::class, 'index'])->name('reports.vehicle-monthly');
    Route::get('reports/vehicle-monthly/export', [VehicleMonthlyReportController::class, 'export'])->name('reports.vehicle-monthly.export');
    Route::get('reports/owner-monthly', [OwnerMonthlyReportController::class, 'index'])->name('reports.owner-monthly');
    Route::get('reports/owner-monthly/export', [OwnerMonthlyReportController::class, 'export'])->name('reports.owner-monthly.export');
});
