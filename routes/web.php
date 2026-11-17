<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Web\Account\Controllers\PasskeyController;
use Modules\Web\Applications\Controllers\ApplicationController;
use Modules\Web\Containers\Controllers\ContainerController;
use Modules\Web\Environments\Controllers\EnvironmentController;
use Modules\Web\Environments\Controllers\EnvironmentOverviewController;
use Modules\Web\Environments\Controllers\EnvironmentSearchController;
use Modules\Web\Environments\Controllers\EnvironmentSettingsController;
use Modules\Web\Home\Controllers\HomeController;
use Modules\Web\Images\Controllers\ImageController;
use Modules\Web\Journal\Controllers\JournalController;
use Modules\Web\Networks\Controllers\NetworkController;
use Modules\Web\Pods\Controllers\PodController;
use Modules\Web\Secrets\Controllers\SecretController;
use Modules\Web\Settings\Controllers\SettingsController;
use Modules\Web\Setup\Controllers\SetupController;
use Modules\Web\Volumes\Controllers\VolumeController;

// Setup, while Pods has no owner
Route::get('/setup', [SetupController::class, 'show'])->name('setup.show');
Route::post('/setup', [SetupController::class, 'store'])->name('setup.store');

// Home
Route::get('/', HomeController::class)->name('home');

// Account
Route::resource('passkeys', PasskeyController::class)->only(['index']);

// Settings
Route::prefix('/settings')->name('settings.')->group(function () {
    Route::get('/', [SettingsController::class, 'index'])->name('index');
    Route::get('/{section}', [SettingsController::class, 'show'])->name('show')->whereIn('section', ['account', 'security', 'general']);
});

// Environments
Route::resource('environments', EnvironmentController::class)->only(['index']);

Route::prefix('/environments/{environment}')->name('environments.')->group(function () {
    Route::get('/', EnvironmentOverviewController::class)->name('overview');
    Route::get('/journal', JournalController::class)->name('journal');
    Route::get('/search', EnvironmentSearchController::class)->name('search');
    Route::get('/settings', [EnvironmentSettingsController::class, 'show'])->name('settings');

    Route::resource('applications', ApplicationController::class)->only(['index', 'create', 'show']);
    Route::resource('containers', ContainerController::class)->only(['index', 'create', 'show']);
    Route::resource('pods', PodController::class)->only(['index']);
    Route::resource('images', ImageController::class)->only(['index']);
    Route::resource('volumes', VolumeController::class)->only(['index']);
    Route::resource('networks', NetworkController::class)->only(['index']);
    Route::resource('secrets', SecretController::class)->only(['index']);
});
