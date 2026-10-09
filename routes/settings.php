<?php

use App\Http\Controllers\Settings\AgencyController;
use App\Http\Controllers\Settings\OwnerController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/security', [SecurityController::class, 'edit'])
        ->middleware(RequirePassword::class)
        ->name('security.edit');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::get('settings/owner', [OwnerController::class, 'edit'])->name('owner.edit');
    Route::patch('settings/owner', [OwnerController::class, 'update'])->name('owner.update');

    Route::get('settings/agency/logo', [AgencyController::class, 'logo'])->name('agency.logo');

    Route::middleware('can:manage-agency')->group(function () {
        Route::get('settings/agency', [AgencyController::class, 'edit'])->name('agency.edit');
        Route::patch('settings/agency', [AgencyController::class, 'update'])->name('agency.update');
        Route::post('settings/agency/logo', [AgencyController::class, 'updateLogo'])->name('agency.logo.update');
        Route::delete('settings/agency/logo', [AgencyController::class, 'destroyLogo'])->name('agency.logo.destroy');
    });

    Route::inertia('settings/appearance', 'settings/Appearance')->name('appearance.edit');
});
