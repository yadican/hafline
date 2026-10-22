<?php

use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\FieldHaulSessionController;
use App\Http\Controllers\Api\HaulController;
use App\Http\Controllers\Api\ProfileController;
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

        Route::post('field-sessions', [FieldHaulSessionController::class, 'store'])->name('api.field-sessions.store');
        Route::get('field-sessions/current', [FieldHaulSessionController::class, 'current'])->name('api.field-sessions.current');
        Route::delete('field-sessions/current', [FieldHaulSessionController::class, 'destroy'])->name('api.field-sessions.destroy');

        Route::get('hauls/options', [HaulController::class, 'options'])->name('api.hauls.options');
        Route::get('hauls/dump-sites', [HaulController::class, 'dumpSites'])->name('api.hauls.dump-sites');
        Route::get('hauls/session', [HaulController::class, 'session'])->name('api.hauls.session');
        Route::get('hauls/{haul}', [HaulController::class, 'show'])->name('api.hauls.show');
        Route::post('hauls/scan', [HaulController::class, 'scan'])
            ->middleware('throttle:20,1')
            ->name('api.hauls.scan');
        Route::post('hauls', [HaulController::class, 'store'])
            ->middleware('throttle:30,1')
            ->name('api.hauls.store');
    });
});
