<?php

use App\Models\Account;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\User;

test('a signed in user only sees the records of their own account', function () {
    $user = User::factory()->for(Account::factory(), 'account')->create();
    $ownTenant = Tenant::factory()->for($user->account, 'account')->create();
    $otherTenant = Tenant::factory()->create();

    $this->actingAs($user);

    expect(Tenant::pluck('id')->all())->toBe([$ownTenant->id])
        ->and(Tenant::find($otherTenant->id))->toBeNull()
        ->and(Tenant::withoutGlobalScope(Tenant::SCOPE)->count())->toBe(2);
});

test('the account filter also applies through relations and joins', function () {
    $user = User::factory()->for(Account::factory(), 'account')->create();
    $ownLease = Lease::factory()->for($user->account, 'account')->create();
    $otherLease = Lease::factory()->create();
    Payment::factory()->for($ownLease)->create();
    Payment::factory()->for($otherLease)->create();

    $this->actingAs($user);

    expect(Payment::whereHas('lease')->count())->toBe(1)
        ->and(Payment::join('leases', 'leases.id', '=', 'payments.lease_id')->count())->toBe(1);
});

test('records created by a signed in user join their account by default', function () {
    $user = User::factory()->for(Account::factory(), 'account')->create();

    $this->actingAs($user);

    $tenant = Tenant::create(['name' => 'Novo inquilino', 'cpf_cnpj' => '52998224725', 'phone' => '87991234567']);

    expect($tenant->account_id)->toBe($user->account_id);
});

test('without a signed in user every account is reached, e.g. by the scheduled payment generation', function () {
    Tenant::factory()->count(2)->create();

    expect(Tenant::count())->toBe(2);
});
