<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('login', fn () => redirect('/sso/login'))
        ->name('login');

    Route::post('login', fn () => redirect('/sso/login'));

    Route::get('forgot-password', fn () => redirect('/sso/login'))
        ->name('password.request');

    Route::post('forgot-password', fn () => redirect('/sso/login'))
        ->name('password.email');

    Route::get('reset-password/{token}', fn () => redirect('/sso/login'))
        ->name('password.reset');

    Route::post('reset-password', fn () => redirect('/sso/login'))
        ->name('password.store');
});

Route::middleware('auth')->group(function () {
    Route::get('logout', fn () => view('auth.logout-confirmation'))
        ->name('logout.confirm');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});
