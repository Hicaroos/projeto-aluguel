<?php

use App\Http\Controllers\ContractTemplateController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\LeaseAdjustmentController;
use App\Http\Controllers\LeaseContractController;
use App\Http\Controllers\LeaseController;
use App\Http\Controllers\LeaseDepositController;
use App\Http\Controllers\LeaseDocumentController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PropertyController;
use App\Http\Controllers\PropertyPhotoController;
use App\Http\Controllers\ReceiptController;
use App\Http\Controllers\TenantController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', fn (Request $request) => to_route($request->user() ? 'dashboard' : 'login'))->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::resource('properties', PropertyController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::post('properties/{property}/photos', [PropertyPhotoController::class, 'store'])->name('properties.photos.store');
    Route::get('property-photos/{photo}', [PropertyPhotoController::class, 'show'])->name('property-photos.show');
    Route::get('property-photos/{photo}/thumbnail', [PropertyPhotoController::class, 'thumbnail'])->name('property-photos.thumbnail');
    Route::patch('property-photos/{photo}/cover', [PropertyPhotoController::class, 'makeCover'])->name('property-photos.cover');
    Route::delete('property-photos/{photo}', [PropertyPhotoController::class, 'destroy'])->name('property-photos.destroy');
    Route::resource('tenants', TenantController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('leases', LeaseController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::patch('leases/{lease}/finish', [LeaseController::class, 'finish'])->name('leases.finish');
    Route::get('leases/{lease}/contract', [LeaseContractController::class, 'show'])->name('leases.contract');
    Route::post('leases/{lease}/adjustments', [LeaseAdjustmentController::class, 'store'])->name('leases.adjustments.store');
    Route::post('leases/{lease}/deposit-settlement', [LeaseDepositController::class, 'store'])->name('leases.deposit-settlement.store');
    Route::post('leases/{lease}/documents', [LeaseDocumentController::class, 'store'])->name('leases.documents.store');
    Route::get('lease-documents/{document}', [LeaseDocumentController::class, 'show'])->name('lease-documents.show');
    Route::get('lease-documents/{document}/download', [LeaseDocumentController::class, 'download'])->name('lease-documents.download');
    Route::delete('lease-documents/{document}', [LeaseDocumentController::class, 'destroy'])->name('lease-documents.destroy');

    Route::post('contract-templates/preview', [ContractTemplateController::class, 'preview'])->name('contract-templates.preview');
    Route::resource('contract-templates', ContractTemplateController::class)->except('show');
    Route::patch('contract-templates/{contract_template}/default', [ContractTemplateController::class, 'makeDefault'])->name('contract-templates.default');

    Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');
    Route::post('payments', [PaymentController::class, 'store'])->name('payments.store');
    Route::delete('payments/{payment}', [PaymentController::class, 'destroy'])->name('payments.destroy');
    Route::post('payments/{payment}/receipts', [ReceiptController::class, 'store'])->name('payments.receipts.store');
    Route::get('receipts/{receipt}', [ReceiptController::class, 'show'])->name('receipts.show');
    Route::delete('receipts/{receipt}', [ReceiptController::class, 'destroy'])->name('receipts.destroy');

    Route::resource('expenses', ExpenseController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::patch('expenses/{expense}/pay', [ExpenseController::class, 'pay'])->name('expenses.pay');
});

require __DIR__.'/settings.php';
