<?php

use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\FieldHaulSessionController;
use App\Http\Controllers\Api\FuelReceiptController;
use App\Http\Controllers\Api\HaulController;
use App\Http\Controllers\Api\MachineWorkController;
use App\Http\Controllers\Api\ProfileController;
use App\Support\ApiPermissions;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('auth/login', [LoginController::class, 'store'])->name('api.auth.login');

    Route::get('hauls/{haul}/photo', [HaulController::class, 'photo'])
        ->middleware('signed')
        ->name('api.hauls.photo');

    Route::middleware('auth:api')->group(function () {
        Route::post('auth/logout', [LoginController::class, 'destroy'])->name('api.auth.logout');
        Route::get('me', [ProfileController::class, 'show'])->name('api.me');
        Route::get('dashboard', [DashboardController::class, 'show'])->name('api.dashboard');

        Route::middleware('permission:'.ApiPermissions::FIELD_HAUL_OPERATE.','.ApiPermissions::GUARD)->group(function () {
            Route::post('field-sessions', [FieldHaulSessionController::class, 'store'])->name('api.field-sessions.store');
            Route::get('field-sessions/current', [FieldHaulSessionController::class, 'current'])->name('api.field-sessions.current');
            Route::delete('field-sessions/current', [FieldHaulSessionController::class, 'destroy'])->name('api.field-sessions.destroy');

            Route::get('hauls/options', [HaulController::class, 'options'])->name('api.hauls.options');
            Route::get('hauls/dump-sites', [HaulController::class, 'dumpSites'])->name('api.hauls.dump-sites');
            Route::get('hauls/vehicles', [HaulController::class, 'vehicles'])->name('api.hauls.vehicles');
            Route::get('hauls/check-plate', [HaulController::class, 'checkPlate'])->name('api.hauls.check-plate');
            Route::get('hauls/session', [HaulController::class, 'session'])->name('api.hauls.session');
            Route::post('hauls/scan', [HaulController::class, 'scan'])
                ->middleware('throttle:20,1')
                ->name('api.hauls.scan');
            Route::post('hauls', [HaulController::class, 'store'])
                ->middleware('throttle:30,1')
                ->name('api.hauls.store');
            Route::get('hauls/{haul}', [HaulController::class, 'show'])->name('api.hauls.show');
            Route::put('hauls/{haul}', [HaulController::class, 'update'])->name('api.hauls.update');
            Route::delete('hauls/{haul}', [HaulController::class, 'destroy'])->name('api.hauls.destroy');

            Route::get('machine-works/options', [MachineWorkController::class, 'options'])->name('api.machine-works.options');
            Route::get('machine-works/machines', [MachineWorkController::class, 'machines'])->name('api.machine-works.machines');
            Route::get('machine-works/check-plate', [MachineWorkController::class, 'checkPlate'])->name('api.machine-works.check-plate');
            Route::get('machine-works/session', [MachineWorkController::class, 'session'])->name('api.machine-works.session');
            Route::post('machine-works/scan', [MachineWorkController::class, 'scan'])
                ->middleware('throttle:20,1')
                ->name('api.machine-works.scan');
            Route::post('machine-works', [MachineWorkController::class, 'store'])
                ->middleware('throttle:30,1')
                ->name('api.machine-works.store');
            Route::get('machine-works/{machineWork}', [MachineWorkController::class, 'show'])->name('api.machine-works.show');
            Route::put('machine-works/{machineWork}', [MachineWorkController::class, 'update'])->name('api.machine-works.update');
            Route::delete('machine-works/{machineWork}', [MachineWorkController::class, 'destroy'])->name('api.machine-works.destroy');

            Route::get('fuel-receipts/options', [FuelReceiptController::class, 'options'])->name('api.fuel-receipts.options');
            Route::get('fuel-receipts/vehicles', [FuelReceiptController::class, 'vehicles'])->name('api.fuel-receipts.vehicles');
            Route::get('fuel-receipts/check-plate', [FuelReceiptController::class, 'checkPlate'])->name('api.fuel-receipts.check-plate');
            Route::get('fuel-receipts/session', [FuelReceiptController::class, 'session'])->name('api.fuel-receipts.session');
            Route::post('fuel-receipts/scan', [FuelReceiptController::class, 'scan'])
                ->middleware('throttle:20,1')
                ->name('api.fuel-receipts.scan');
            Route::post('fuel-receipts', [FuelReceiptController::class, 'store'])
                ->middleware('throttle:30,1')
                ->name('api.fuel-receipts.store');
            Route::get('fuel-receipts/{fuelReceipt}', [FuelReceiptController::class, 'show'])->name('api.fuel-receipts.show');
            Route::put('fuel-receipts/{fuelReceipt}', [FuelReceiptController::class, 'update'])->name('api.fuel-receipts.update');
            Route::delete('fuel-receipts/{fuelReceipt}', [FuelReceiptController::class, 'destroy'])->name('api.fuel-receipts.destroy');
        });
    });
});
