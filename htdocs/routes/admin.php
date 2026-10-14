<?php

use App\Http\Controllers\Admin\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ExampleController;
use App\Http\Controllers\Admin\HaulController;
use App\Http\Controllers\Admin\HaulLookupController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::resource('examples', ExampleController::class)->except(['show']);

    Route::get('hauls/lookups/dump-sites', [HaulLookupController::class, 'dumpSites'])->name('hauls.lookups.dump-sites');
    Route::get('hauls/lookups/vehicles', [HaulLookupController::class, 'vehicles'])->name('hauls.lookups.vehicles');
    Route::resource('hauls', HaulController::class)->except(['show']);
});
