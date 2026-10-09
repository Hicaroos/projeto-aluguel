<?php

use App\Http\Controllers\BranchController;
use App\Http\Controllers\BranchSelectionController;
use App\Http\Controllers\ContractTemplateController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\LeaseAdjustmentController;
use App\Http\Controllers\LeaseContractController;
use App\Http\Controllers\LeaseController;
use App\Http\Controllers\LeaseDepositController;
use App\Http\Controllers\LeaseDocumentController;
use App\Http\Controllers\LeaseRenewalController;
use App\Http\Controllers\OwnerController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PropertyController;
use App\Http\Controllers\PropertyPhotoController;
use App\Http\Controllers\ReceiptController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\TenantController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', fn (Request $request) => to_route($request->user() ? 'dashboard' : 'login'))->name('home');

Route::get('invitation/{token}', [InvitationController::class, 'show'])->name('invitation.show');
Route::post('invitation/{token}', [InvitationController::class, 'accept'])->middleware('throttle:6,1')->name('invitation.accept');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::put('branch-selection', [BranchSelectionController::class, 'update'])->name('branch-selection.update');

    Route::get('owners', [OwnerController::class, 'index'])->name('owners.index');
    Route::get('properties', [PropertyController::class, 'index'])->name('properties.index');
    Route::get('property-photos/{photo}', [PropertyPhotoController::class, 'show'])->name('property-photos.show');
    Route::get('property-photos/{photo}/thumbnail', [PropertyPhotoController::class, 'thumbnail'])->name('property-photos.thumbnail');
    Route::get('tenants', [TenantController::class, 'index'])->name('tenants.index');
    Route::get('leases', [LeaseController::class, 'index'])->name('leases.index');
    Route::get('leases/{lease}/contract', [LeaseContractController::class, 'show'])->name('leases.contract');
    Route::get('leases/{lease}/renewals/{renewal}/amendment', [LeaseRenewalController::class, 'amendment'])->scopeBindings()->name('leases.renewals.amendment');
    Route::get('lease-documents/{document}', [LeaseDocumentController::class, 'show'])->name('lease-documents.show');
    Route::get('lease-documents/{document}/download', [LeaseDocumentController::class, 'download'])->name('lease-documents.download');

    Route::middleware('can:manage-rentals')->group(function () {
        Route::post('owners/link', [OwnerController::class, 'link'])->name('owners.link');
        Route::resource('owners', OwnerController::class)->only(['store', 'update', 'destroy']);
        Route::resource('properties', PropertyController::class)->only(['store', 'update', 'destroy']);
        Route::post('properties/{property}/photos', [PropertyPhotoController::class, 'store'])->name('properties.photos.store');
        Route::patch('property-photos/{photo}/cover', [PropertyPhotoController::class, 'makeCover'])->name('property-photos.cover');
        Route::delete('property-photos/{photo}', [PropertyPhotoController::class, 'destroy'])->name('property-photos.destroy');
        Route::post('tenants/link', [TenantController::class, 'link'])->name('tenants.link');
        Route::resource('tenants', TenantController::class)->only(['store', 'update', 'destroy']);
        Route::resource('leases', LeaseController::class)->only(['store', 'update', 'destroy']);
        Route::patch('leases/{lease}/finish', [LeaseController::class, 'finish'])->name('leases.finish');
        Route::post('leases/{lease}/adjustments', [LeaseAdjustmentController::class, 'store'])->name('leases.adjustments.store');
        Route::post('leases/{lease}/renewals', [LeaseRenewalController::class, 'store'])->name('leases.renewals.store');
        Route::post('leases/{lease}/documents', [LeaseDocumentController::class, 'store'])->name('leases.documents.store');
        Route::delete('lease-documents/{document}', [LeaseDocumentController::class, 'destroy'])->name('lease-documents.destroy');

        Route::post('contract-templates/preview', [ContractTemplateController::class, 'preview'])->name('contract-templates.preview');
        Route::resource('contract-templates', ContractTemplateController::class)->except('show');
        Route::patch('contract-templates/{contract_template}/default', [ContractTemplateController::class, 'makeDefault'])->name('contract-templates.default');
    });

    Route::middleware('can:register-receipts')->group(function () {
        Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');
        Route::post('payments/{payment}/receipts', [ReceiptController::class, 'store'])->name('payments.receipts.store');
        Route::get('receipts/{receipt}', [ReceiptController::class, 'show'])->name('receipts.show');
        Route::get('receipts/{receipt}/pdf', [ReceiptController::class, 'pdf'])->name('receipts.pdf');
    });

    Route::middleware('can:manage-finance')->group(function () {
        Route::post('payments', [PaymentController::class, 'store'])->name('payments.store');
        Route::delete('payments/{payment}', [PaymentController::class, 'destroy'])->name('payments.destroy');
        Route::delete('receipts/{receipt}', [ReceiptController::class, 'destroy'])->name('receipts.destroy');
        Route::post('leases/{lease}/deposit-settlement', [LeaseDepositController::class, 'store'])->name('leases.deposit-settlement.store');

        Route::resource('expenses', ExpenseController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::patch('expenses/{expense}/pay', [ExpenseController::class, 'pay'])->name('expenses.pay');
    });

    Route::middleware('can:manage-agency')->group(function () {
        Route::resource('branches', BranchController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::patch('branches/{branch}/status', [BranchController::class, 'toggleStatus'])->name('branches.status');

        Route::get('team', [TeamController::class, 'index'])->name('team.index');
        Route::post('team', [TeamController::class, 'store'])->name('team.store');
        Route::put('team/{member}', [TeamController::class, 'update'])->name('team.update');
        Route::patch('team/{member}/status', [TeamController::class, 'toggleStatus'])->name('team.status');
        Route::post('team/{member}/invitation', [TeamController::class, 'renewInvitation'])->name('team.invitation');
        Route::delete('team/{member}', [TeamController::class, 'destroy'])->name('team.destroy');
    });
});

require __DIR__.'/settings.php';
