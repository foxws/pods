<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Web\Applications\Controllers\ApplicationOperationController;
use Modules\Web\Environments\Controllers\EnvironmentController;
use Modules\Web\Secrets\Controllers\SecretController;
use Modules\Web\Settings\Controllers\ApplicationSettingsController;

// Settings
Route::prefix('/settings')->name('settings.')->group(function () {
    Route::singleton('application', ApplicationSettingsController::class)->only(['show', 'update']);
});

// Environments
Route::resource('environments', EnvironmentController::class)->only(['store', 'destroy']);

Route::prefix('/environments/{environment}')->name('environments.')->group(function () {
    Route::post('/applications/{application}/{operation}', ApplicationOperationController::class)->name('applications.operation');
    Route::post('/secrets', [SecretController::class, 'store'])->name('secrets.store');
});
