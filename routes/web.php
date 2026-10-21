<?php

use App\Http\Controllers\BranchController;
use App\Http\Controllers\BranchSelectionController;
use App\Http\Controllers\ContentSecurityReportController;
use App\Http\Controllers\ContractTemplateController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DealController;
use App\Http\Controllers\DealLeaseController;
use App\Http\Controllers\DealProposalController;
use App\Http\Controllers\DealVisitController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\ExpenseDocumentController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\LeaseAdjustmentController;
use App\Http\Controllers\LeaseContractController;
use App\Http\Controllers\LeaseController;
use App\Http\Controllers\LeaseDepositController;
use App\Http\Controllers\LeaseDocumentController;
use App\Http\Controllers\LeaseRenewalController;
use App\Http\Controllers\LedgerAdjustmentController;
use App\Http\Controllers\OwnerController;
use App\Http\Controllers\OwnerSelectionController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PayoutController;
use App\Http\Controllers\PropertyController;
use App\Http\Controllers\PropertyPhotoController;
use App\Http\Controllers\ReceiptController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\SaleDocumentController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\TenantController;
use App\Http\Controllers\WelcomeController;
use App\Http\Middleware\IgnoreOwnerSelection;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Route;

Route::get('/', WelcomeController::class)->name('home');

// Browsers post here what the content policy blocks; they send no CSRF token.
Route::post('csp-report', ContentSecurityReportController::class)
    ->withoutMiddleware(PreventRequestForgery::class)
    ->middleware('throttle:30,1')
    ->name('csp-report');

Route::get('invitation/{token}', [InvitationController::class, 'show'])->name('invitation.show');
Route::post('invitation/{token}', [InvitationController::class, 'accept'])->middleware('throttle:6,1')->name('invitation.accept');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::put('branch-selection', [BranchSelectionController::class, 'update'])->name('branch-selection.update');
    Route::get('owner-selection', [OwnerSelectionController::class, 'index'])->name('owner-selection.index');
    Route::put('owner-selection', [OwnerSelectionController::class, 'update'])->name('owner-selection.update');

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

    // Leads, deals and sales don't belong to an owner: they show everything whatever owner is selected.
    Route::middleware(IgnoreOwnerSelection::class)->group(function () {
        Route::get('leads', [LeadController::class, 'index'])->name('leads.index');
        Route::get('deals', [DealController::class, 'index'])->name('deals.index');
        Route::get('sales', [SaleController::class, 'index'])->name('sales.index');
        Route::get('sales/{sale}/generate/{kind}', [SaleDocumentController::class, 'generate'])->name('sales.generate');
        Route::get('sale-documents/{document}', [SaleDocumentController::class, 'show'])->name('sale-documents.show');
        Route::get('sale-documents/{document}/download', [SaleDocumentController::class, 'download'])->name('sale-documents.download');

        Route::middleware('can:manage-sales')->group(function () {
            Route::resource('leads', LeadController::class)->only(['store', 'update', 'destroy']);
            Route::put('deal-stages', [DealController::class, 'updateStages'])->name('deal-stages.update');
            Route::resource('deals', DealController::class)->only(['store', 'update', 'destroy']);
            Route::patch('deals/{deal}/move', [DealController::class, 'move'])->name('deals.move');
            Route::post('deals/{deal}/visits', [DealVisitController::class, 'store'])->name('deals.visits.store');
            Route::put('deal-visits/{visit}', [DealVisitController::class, 'update'])->name('deal-visits.update');
            Route::delete('deal-visits/{visit}', [DealVisitController::class, 'destroy'])->name('deal-visits.destroy');
            Route::post('deals/{deal}/proposals', [DealProposalController::class, 'store'])->name('deals.proposals.store');
            Route::put('deal-proposals/{proposal}', [DealProposalController::class, 'update'])->name('deal-proposals.update');
            Route::delete('deal-proposals/{proposal}', [DealProposalController::class, 'destroy'])->name('deal-proposals.destroy');
            Route::post('deals/{deal}/sale', [SaleController::class, 'store'])->name('deals.sale.store');
            Route::post('sales', [SaleController::class, 'storeDirect'])->name('sales.store');
            Route::put('sales/{sale}', [SaleController::class, 'update'])->name('sales.update');
            Route::delete('sales/{sale}', [SaleController::class, 'destroy'])->name('sales.destroy');
            Route::post('sales/{sale}/documents', [SaleDocumentController::class, 'store'])->name('sales.documents.store');
            Route::delete('sale-documents/{document}', [SaleDocumentController::class, 'destroy'])->name('sale-documents.destroy');
        });
    });

    Route::middleware('can:manage-rentals')->group(function () {
        Route::get('deals/{deal}/lease', DealLeaseController::class)->name('deals.lease');
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
        Route::get('expenses/{expense}/service-receipt', [ExpenseDocumentController::class, 'receipt'])->name('expenses.service-receipt');
        Route::post('expenses/{expense}/documents', [ExpenseDocumentController::class, 'store'])->name('expenses.documents.store');
        Route::get('expense-documents/{document}', [ExpenseDocumentController::class, 'show'])->name('expense-documents.show');
        Route::get('expense-documents/{document}/download', [ExpenseDocumentController::class, 'download'])->name('expense-documents.download');
        Route::delete('expense-documents/{document}', [ExpenseDocumentController::class, 'destroy'])->name('expense-documents.destroy');

        Route::get('payouts', [PayoutController::class, 'index'])->name('payouts.index');
        Route::get('payouts/{owner}', [PayoutController::class, 'show'])->name('payouts.show');
        Route::get('payouts/{owner}/pdf', [PayoutController::class, 'pdf'])->name('payouts.pdf');
        Route::post('payouts/{owner}', [PayoutController::class, 'store'])->name('payouts.store');
        Route::delete('owner-payouts/{payout}', [PayoutController::class, 'destroy'])->name('owner-payouts.destroy');
        Route::post('payouts/{owner}/adjustments', [LedgerAdjustmentController::class, 'store'])->name('payouts.adjustments.store');
        Route::delete('ledger-entries/{entry}', [LedgerAdjustmentController::class, 'destroy'])->name('ledger-entries.destroy');
    });

    Route::middleware(['can:manage-agency', IgnoreOwnerSelection::class])->group(function () {
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
require __DIR__.'/showcase.php';
