<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Web\Applications\Controllers\ApplicationController;
use Modules\Web\Applications\Controllers\ApplicationDropInController;
use Modules\Web\Applications\Controllers\ApplicationInstallationController;
use Modules\Web\Applications\Controllers\ApplicationOnDemandController;
use Modules\Web\Applications\Controllers\ApplicationOperationController;
use Modules\Web\Applications\Controllers\ApplicationPinController;
use Modules\Web\Applications\Controllers\ApplicationRoutesController;
use Modules\Web\Applications\Controllers\ApplicationSetupController;
use Modules\Web\Applications\Controllers\ApplicationSourceController;
use Modules\Web\Applications\Controllers\ApplicationTimerController;
use Modules\Web\Applications\Controllers\ApplicationUpdateController;
use Modules\Web\Containers\Controllers\ContainerOperationController;
use Modules\Web\Containers\Controllers\ContainerPruneController;
use Modules\Web\Environments\Controllers\EnvironmentController;
use Modules\Web\Environments\Controllers\EnvironmentLpodController;
use Modules\Web\Environments\Controllers\EnvironmentProxyController;
use Modules\Web\Environments\Controllers\EnvironmentSettingsController;
use Modules\Web\Environments\Controllers\EnvironmentVerificationController;
use Modules\Web\Images\Controllers\ImageController;
use Modules\Web\Images\Controllers\ImagePruneController;
use Modules\Web\Images\Controllers\ImageUpdateController;
use Modules\Web\Networks\Controllers\NetworkController;
use Modules\Web\Networks\Controllers\NetworkPruneController;
use Modules\Web\Notifications\Controllers\NotificationBulkController;
use Modules\Web\Notifications\Controllers\NotificationController;
use Modules\Web\Quadlets\Controllers\QuadletCheckController;
use Modules\Web\Quadlets\Controllers\QuadletController;
use Modules\Web\Quadlets\Controllers\QuadletFetchController;
use Modules\Web\Secrets\Controllers\SecretController;
use Modules\Web\Settings\Controllers\ApplicationSettingsController;
use Modules\Web\Settings\Controllers\CatalogSettingsController;
use Modules\Web\Settings\Controllers\ProxyDefaultsController;
use Modules\Web\Volumes\Controllers\VolumeController;
use Modules\Web\Volumes\Controllers\VolumeExportController;
use Modules\Web\Volumes\Controllers\VolumePruneController;

// Settings
Route::prefix('/settings')->name('settings.')->group(function () {
    Route::singleton('application', ApplicationSettingsController::class)->only(['show', 'update']);
    Route::singleton('proxy', ProxyDefaultsController::class)->only(['show', 'update']);
    Route::singleton('catalog', CatalogSettingsController::class)->only(['show', 'update']);
});

// Notifications
Route::prefix('/notifications')->name('notifications.')->group(function () {
    Route::put('/', [NotificationBulkController::class, 'update'])->name('read');
    Route::delete('/', [NotificationBulkController::class, 'destroy'])->name('clear');
    Route::put('/{notification}', [NotificationController::class, 'update'])->name('update');
    Route::delete('/{notification}', [NotificationController::class, 'destroy'])->name('destroy');
});

// Environments
Route::resource('environments', EnvironmentController::class)->only(['store', 'destroy']);

Route::prefix('/environments/{environment}')->name('environments.')->group(function () {
    // Settings
    Route::put('/settings', [EnvironmentSettingsController::class, 'update'])->name('settings.update');
    Route::put('/lpod', [EnvironmentLpodController::class, 'update'])->name('lpod.update');
    Route::post('/verification', [EnvironmentVerificationController::class, 'store'])->name('verification.store');

    // Proxy
    Route::prefix('/proxy')->name('proxy.')->group(function () {
        Route::post('/', [EnvironmentProxyController::class, 'store'])->name('store');
        Route::put('/', [EnvironmentProxyController::class, 'update'])->name('update');
        Route::get('/certificate', [EnvironmentProxyController::class, 'certificate'])->name('certificate');
    });

    // Applications
    Route::resource('applications', ApplicationController::class)->only(['destroy']);
    Route::put('/applications/{application}/installation', [ApplicationInstallationController::class, 'update'])->name('applications.installation.update');
    Route::put('/applications/{application}/ondemand', [ApplicationOnDemandController::class, 'update'])->name('applications.ondemand.update');
    Route::delete('/applications/{application}/ondemand', [ApplicationOnDemandController::class, 'destroy'])->name('applications.ondemand.destroy');
    Route::put('/applications/{application}/source', [ApplicationSourceController::class, 'update'])->name('applications.source.update');
    Route::put('/applications/{application}/routes', [ApplicationRoutesController::class, 'update'])->name('applications.routes.update');
    Route::put('/applications/{application}/drop-ins', [ApplicationDropInController::class, 'update'])->name('applications.drop-ins.update');
    Route::delete('/applications/{application}/drop-ins', [ApplicationDropInController::class, 'destroy'])->name('applications.drop-ins.destroy');
    Route::put('/applications/{application}/pin', [ApplicationPinController::class, 'update'])->where('application', '[^/]{1,255}')->name('applications.pin.update');
    Route::delete('/applications/{application}/pin', [ApplicationPinController::class, 'destroy'])->where('application', '[^/]{1,255}')->name('applications.pin.destroy');
    Route::post('/applications/{application}/update', [ApplicationUpdateController::class, 'store'])->name('applications.update.store');
    Route::post('/applications/{application}/setup/{step}', [ApplicationSetupController::class, 'store'])->name('applications.setup.store');
    Route::post('/applications/{application}/timers/{timer}/run', [ApplicationTimerController::class, 'store'])->name('applications.timers.run');
    Route::post('/applications/{application}/{operation}', ApplicationOperationController::class)->name('applications.operation');

    // Containers
    Route::post('/containers/prune', ContainerPruneController::class)->name('containers.prune');
    Route::post('/containers/{container}/{operation}', ContainerOperationController::class)->name('containers.operation');

    // Images
    Route::prefix('/images')->name('images.')->group(function () {
        Route::post('/prune', ImagePruneController::class)->name('prune');
        Route::post('/updates', ImageUpdateController::class)->name('updates');
    });
    Route::resource('images', ImageController::class)->only(['store', 'destroy']);

    // Volumes
    Route::post('/volumes/prune', VolumePruneController::class)->name('volumes.prune');
    Route::resource('volumes', VolumeController::class)->only(['destroy']);
    Route::prefix('/volume-exports')->name('volume-exports.')->scopeBindings()->group(function () {
        Route::post('/', [VolumeExportController::class, 'store'])->name('store');
        Route::get('/{volumeExport}/download', [VolumeExportController::class, 'download'])->name('download');
        Route::delete('/{volumeExport}', [VolumeExportController::class, 'destroy'])->name('destroy');
    });

    // Networks
    Route::post('/networks/prune', NetworkPruneController::class)->name('networks.prune');
    Route::resource('networks', NetworkController::class)->only(['destroy']);

    // Quadlets
    Route::prefix('/quadlets')->name('quadlets.')->group(function () {
        Route::post('/check', QuadletCheckController::class)->name('check');
        Route::post('/fetch', QuadletFetchController::class)->name('fetch');
    });
    Route::resource('quadlets', QuadletController::class)->only(['store']);

    // Secrets
    Route::resource('secrets', SecretController::class)->only(['store', 'update', 'destroy']);
});
