<?php

use App\Http\Controllers\Auth\AuthController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);

    // The admin sign-in's code step (Doc 15 section 15.2).
    Route::get('/login/two-factor', [AuthController::class, 'showChallenge'])->name('login.two-factor');
    Route::post('/login/two-factor', [AuthController::class, 'challenge']);
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});
