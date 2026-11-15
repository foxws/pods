<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Web\Account\Controllers\PasskeyController;
use Modules\Web\Applications\Controllers\ApplicationController;
use Modules\Web\Environments\Controllers\EnvironmentController;
use Modules\Web\Home\Controllers\HomeController;
use Modules\Web\Secrets\Controllers\SecretController;

// Home
Route::get('/', HomeController::class)->name('home');

// Account
Route::resource('passkeys', PasskeyController::class)->only(['index']);

// Environments
Route::resource('environments', EnvironmentController::class)->only(['index']);

Route::prefix('/environments/{environment}')->name('environments.')->group(function () {
    Route::get('/applications', [ApplicationController::class, 'index'])->name('applications.index');
    Route::get('/applications/{application}', [ApplicationController::class, 'show'])->name('applications.show');
    Route::get('/secrets', [SecretController::class, 'index'])->name('secrets.index');
});
