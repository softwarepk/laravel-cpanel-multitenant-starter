<?php

use App\Http\Controllers\CentralActivityController;
use App\Http\Controllers\CentralAuthController;
use App\Http\Controllers\CentralDashboardController;
use App\Http\Controllers\CentralSettingsController;
use App\Http\Controllers\CentralTenantController;
use App\Http\Controllers\CentralTenantLifecycleController;
use App\Http\Controllers\CentralTenantPagesController;
use App\Services\InstallationState;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::prefix('central')
    ->middleware('central.domain')
    ->group(function (): void {
        Route::get('login', [CentralAuthController::class, 'create'])->name('central.login');
        Route::post('login', [CentralAuthController::class, 'store'])->name('central.login.store');

        Route::middleware('central.admin')->group(function (): void {
            Route::get('/', [CentralDashboardController::class, 'index'])->name('central.home');
            Route::post('logout', [CentralAuthController::class, 'destroy'])->name('central.logout');

            Route::get('tenants', [CentralTenantPagesController::class, 'index'])->name('central.tenants.index');
            Route::get('tenants/create', [CentralTenantPagesController::class, 'create'])->name('central.tenants.create');
            Route::post('tenants', [CentralTenantController::class, 'store'])->middleware('cpanel.provisioning')->name('central.tenants.store');
            Route::get('tenants/provisioning/{tenantId}', [CentralTenantController::class, 'provisioningStatus'])->name('central.tenants.provisioning.status');
            Route::get('tenants/{tenant}', [CentralTenantPagesController::class, 'show'])->name('central.tenants.show');
            Route::delete('tenants/{tenant}', [CentralTenantLifecycleController::class, 'destroy'])->name('central.tenants.destroy');
            Route::post('tenants/{tenant}/https/check', [CentralTenantController::class, 'checkPlatformHttps'])->name('central.tenants.https.check');
            Route::post('tenants/{tenant}/domains', [CentralTenantController::class, 'addCustomDomain'])->name('central.tenants.domains.store');
            Route::post('tenants/{tenant}/domains/{domain}/verify', [CentralTenantController::class, 'verifyCustomDomain'])->name('central.tenants.domains.verify');
            Route::post('tenants/{tenant}/domains/{domain}/primary', [CentralTenantController::class, 'makePrimaryDomain'])->name('central.tenants.domains.primary');
            Route::delete('tenants/{tenant}/domains/{domain}', [CentralTenantController::class, 'deleteCustomDomain'])->name('central.tenants.domains.destroy');
            Route::post('tenants/{tenant}/retry', [CentralTenantController::class, 'retry'])->name('central.tenants.retry');
            Route::post('tenants/{tenant}/suspend', [CentralTenantLifecycleController::class, 'suspend'])->name('central.tenants.suspend');
            Route::post('tenants/{tenant}/activate', [CentralTenantLifecycleController::class, 'activate'])->name('central.tenants.activate');

            Route::get('activity', [CentralActivityController::class, 'index'])->name('central.activity.index');
            Route::get('settings', [CentralSettingsController::class, 'index'])->name('central.settings.index');
            Route::put('settings', [CentralSettingsController::class, 'update'])->name('central.settings.update');
        });
    });

Route::get('/', function (InstallationState $installation): RedirectResponse {
    if ($installation->requiresInstallation()) {
        return new RedirectResponse('/install');
    }

    return redirect()->route(Auth::check() ? 'dashboard' : 'login');
})->name('home');

$applicationMiddleware = ['auth'];
if (config('fortify.features') && in_array(Features::emailVerification(), config('fortify.features', []), true)) {
    $applicationMiddleware[] = 'verified';
}

Route::middleware($applicationMiddleware)->group(function (): void {
    Route::view('dashboard', 'dashboard')->name('dashboard');
    Route::view('patterns', 'patterns')->name('patterns');
});

require __DIR__.'/settings.php';
