<?php

use App\Http\Controllers\LeaseController;
use App\Http\Controllers\PropertyController;
use App\Http\Controllers\TenantController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    Route::resource('properties', PropertyController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('tenants', TenantController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('leases', LeaseController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::patch('leases/{lease}/finish', [LeaseController::class, 'finish'])->name('leases.finish');
});

require __DIR__.'/settings.php';
