<?php

use App\Http\Controllers\MediaController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('admin.login');
});

Route::get('/media/{path}', [MediaController::class, 'show'])
    ->where('path', '.*')
    ->name('media.show');
