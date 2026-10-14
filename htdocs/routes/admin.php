<?php

use App\Http\Controllers\Admin\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Admin\BankController;
use App\Http\Controllers\Admin\CashCategoryController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DumpSiteController;
use App\Http\Controllers\Admin\ExampleController;
use App\Http\Controllers\Admin\FuelStationController;
use App\Http\Controllers\Admin\HaulController;
use App\Http\Controllers\Admin\HaulLookupController;
use App\Http\Controllers\Admin\HaulRouteController;
use App\Http\Controllers\Admin\PaymentTypeController;
use App\Http\Controllers\Admin\RouteVehiclePriceController;
use App\Http\Controllers\Admin\SiteController;
use App\Http\Controllers\Admin\VehicleClassController;
use App\Http\Controllers\Admin\VehicleController;
use App\Http\Controllers\Admin\VehicleOwnerController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::resource('examples', ExampleController::class)->except(['show']);

    Route::resource('payment-types', PaymentTypeController::class)->except(['show']);
    Route::resource('cash-categories', CashCategoryController::class)->except(['show']);
    Route::resource('banks', BankController::class)->except(['show']);

    Route::resource('vehicles', VehicleController::class)->except(['show']);
    Route::resource('vehicle-owners', VehicleOwnerController::class)->except(['show']);
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
});
