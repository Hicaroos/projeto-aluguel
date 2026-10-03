<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LeaseController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PropertyController;
use App\Http\Controllers\ReceiptController;
use App\Http\Controllers\TenantController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', fn (Request $request) => to_route($request->user() ? 'dashboard' : 'login'))->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::resource('properties', PropertyController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('tenants', TenantController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('leases', LeaseController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::patch('leases/{lease}/finish', [LeaseController::class, 'finish'])->name('leases.finish');

    Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');
    Route::post('payments/{payment}/receipts', [ReceiptController::class, 'store'])->name('payments.receipts.store');
    Route::delete('receipts/{receipt}', [ReceiptController::class, 'destroy'])->name('receipts.destroy');
});

require __DIR__.'/settings.php';
