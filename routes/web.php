<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\CounterpartyController;
use App\Http\Controllers\IntegrationSettingsController;
use App\Http\Controllers\OrderController;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function (): RedirectResponse {
    return redirect()->route(Auth::check() ? 'dashboard' : 'login');
});

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::middleware('auth')->group(function (): void {
    Route::view('/dashboard', 'dashboard')
        ->middleware('permission:dashboard.view')
        ->name('dashboard');

    Route::get('/counterparties', [CounterpartyController::class, 'index'])
        ->middleware('permission:counterparties.view')
        ->name('counterparties.index');
    Route::get('/counterparties/{counterparty}', [CounterpartyController::class, 'show'])
        ->middleware('permission:counterparties.view')
        ->name('counterparties.show');

    Route::get('/counterparties/create', [CounterpartyController::class, 'create'])
        ->middleware('permission:counterparties.manage')
        ->name('counterparties.create');
    Route::post('/counterparties/dadata/autofill', [CounterpartyController::class, 'autofillByInn'])
        ->middleware('permission:counterparties.manage')
        ->name('counterparties.dadata.autofill');
    Route::post('/counterparties', [CounterpartyController::class, 'store'])
        ->middleware('permission:counterparties.manage')
        ->name('counterparties.store');
    Route::get('/counterparties/{counterparty}/edit', [CounterpartyController::class, 'edit'])
        ->middleware('permission:counterparties.manage')
        ->name('counterparties.edit');
    Route::put('/counterparties/{counterparty}', [CounterpartyController::class, 'update'])
        ->middleware('permission:counterparties.manage')
        ->name('counterparties.update');

    Route::get('/orders', [OrderController::class, 'index'])
        ->middleware('permission:orders.view')
        ->name('orders.index');
    Route::get('/orders/{order}', [OrderController::class, 'show'])
        ->middleware('permission:orders.view')
        ->name('orders.show');

    Route::get('/orders/create', [OrderController::class, 'create'])
        ->middleware('permission:orders.manage')
        ->name('orders.create');
    Route::get('/orders/{order}/edit', [OrderController::class, 'edit'])
        ->middleware('permission:orders.manage')
        ->name('orders.edit');

    Route::get('/settings/integrations/dadata', [IntegrationSettingsController::class, 'editDadata'])
        ->middleware('permission:settings.integrations.manage')
        ->name('settings.dadata.edit');
    Route::put('/settings/integrations/dadata', [IntegrationSettingsController::class, 'updateDadata'])
        ->middleware('permission:settings.integrations.manage')
        ->name('settings.dadata.update');
    Route::post('/settings/integrations/dadata/test', [IntegrationSettingsController::class, 'testDadata'])
        ->middleware('permission:settings.integrations.manage')
        ->name('settings.dadata.test');

    Route::get('/driver/panel', function () {
        return view('driver.panel');
    })
        ->middleware('permission:driver.panel.view')
        ->name('driver.panel');
});
