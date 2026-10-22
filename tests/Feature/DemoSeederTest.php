<?php

use App\Enums\LeaseStatus;
use App\Enums\PaymentStatus;
use App\Models\Account;
use App\Models\Deal;
use App\Models\Lease;
use App\Models\LeaseAdjustment;
use App\Models\LeaseDocument;
use App\Models\OwnerPayout;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Sale;
use App\Models\SentMessage;
use App\Models\ShowcaseRequest;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

test('the demo data fills every part of the system', function () {
    $this->seed(DemoSeeder::class);

    $agency = Account::where('name', 'Imobizilla')->firstOrFail();
    $properties = Property::withoutGlobalScopes()->where('account_id', $agency->id);

    expect(Account::count())->toBe(2)
        ->and(User::where('email', 'admin@imobizilla.test')->exists())->toBeTrue()
        ->and(User::whereNotNull('invitation_token')->exists())->toBeTrue()
        ->and((clone $properties)->whereNotNull('published_at')->count())->toBeGreaterThanOrEqual(8)
        ->and(Lease::withoutGlobalScopes()->where('account_id', $agency->id)->where('status', LeaseStatus::Active)->count())->toBe(5)
        ->and(Lease::withoutGlobalScopes()->where('status', LeaseStatus::Ended)->whereNotNull('deposit_settled_on')->exists())->toBeTrue()
        ->and(LeaseAdjustment::count())->toBe(1)
        ->and(LeaseDocument::count())->toBeGreaterThanOrEqual(6)
        ->and(OwnerPayout::withoutGlobalScopes()->whereNotNull('receipt_path')->exists())->toBeTrue()
        ->and(Sale::withoutGlobalScopes()->count())->toBe(2)
        ->and(Deal::withoutGlobalScopes()->whereNull('closed_at')->count())->toBeGreaterThanOrEqual(7)
        ->and(ShowcaseRequest::count())->toBe(4)
        ->and(SentMessage::count())->toBeGreaterThan(0);

    $overdue = Payment::withoutGlobalScopes()
        ->where('account_id', $agency->id)
        ->where('status', PaymentStatus::Pending)
        ->whereDate('due_date', '<', today());

    expect($overdue->exists())->toBeTrue();
});

test('every page of the agency opens with the demo data', function () {
    $this->seed(DemoSeeder::class);

    $admin = User::where('email', 'admin@imobizilla.test')->firstOrFail();
    $pages = ['dashboard', 'properties.index', 'owners.index', 'tenants.index', 'leases.index', 'payments.index', 'payouts.index', 'expenses.index', 'leads.index', 'deals.index', 'sales.index', 'team.index', 'branches.index', 'contract-templates.index'];

    foreach ($pages as $page) {
        $this->actingAs($admin)->get(route($page))->assertOk();
    }

    $this->get(route('showcase.home', 'imobizilla'))->assertOk();
    $this->get(route('showcase.index', 'imobizilla'))->assertOk();
});

test('every document the demo data keeps has its file', function () {
    $this->seed(DemoSeeder::class);

    LeaseDocument::all()->each(fn (LeaseDocument $document) => Storage::disk(LeaseDocument::DISK)->assertExists($document->path));
});
