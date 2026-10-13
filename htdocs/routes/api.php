<?php

use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ProfileController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('auth/login', [LoginController::class, 'store'])->name('api.auth.login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('auth/logout', [LoginController::class, 'destroy'])->name('api.auth.logout');
        Route::get('me', [ProfileController::class, 'show'])->name('api.me');
        Route::get('dashboard', [DashboardController::class, 'show'])->name('api.dashboard');
    });
});
