<?php

use App\Enums\Role;
use App\Models\Account;
use App\Models\Branch;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * An agency whose owner is its first administrator, with two branches.
 *
 * @return array{account: Account, owner: User, ouricuri: Branch, araripina: Branch}
 */
function teamScenario(): array
{
    $account = Account::factory()->agency()->create(['name' => 'Imobiliária Silva']);

    return [
        'account' => $account,
        'owner' => User::factory()->for($account, 'account')->create(),
        'ouricuri' => Branch::factory()->for($account, 'account')->create(['name' => 'Ouricuri']),
        'araripina' => Branch::factory()->for($account, 'account')->create(['name' => 'Araripina']),
    ];
}

test('agency admins see their team with roles, branches and access', function () {
    ['account' => $account, 'owner' => $owner, 'ouricuri' => $ouricuri] = teamScenario();
    $agent = User::factory()->for($account, 'account')->withRole(Role::Agent)->create(['name' => 'Bruno Corretor']);
    $agent->branches()->attach($ouricuri);
    User::factory()->for($account, 'account')->withRole(Role::Finance)->invited()->create(['name' => 'Carla Financeiro']);
    User::factory()->for($account, 'account')->withRole(Role::General)->deactivated()->create(['name' => 'Ana Antiga']);
    User::factory()->create();

    $this->actingAs($owner)
        ->get(route('team.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('team/Index')
            ->has('members', 4)
            ->where('members.0.name', 'Bruno Corretor')
            ->where('members.0.role', 'agent')
            ->where('members.0.branches.0.name', 'Ouricuri')
            ->where('members.0.status', 'active')
            ->where('members.1.name', 'Carla Financeiro')
            ->where('members.1.status', 'pending')
            ->where('members.2.id', $owner->id)
            ->where('members.2.is_owner', true)
            ->where('members.2.is_self', true)
            ->where('members.3.name', 'Ana Antiga')
            ->where('members.3.status', 'inactive')
            ->has('roles', 4)
            ->has('branches', 2)
        );
});

test('only agency administrators manage the team', function (Role $role) {
    ['account' => $account] = teamScenario();
    $member = User::factory()->for($account, 'account')->withRole($role)->create();

    $this->actingAs($member)->get(route('team.index'))->assertForbidden();
    $this->actingAs($member)->post(route('team.store'), ['name' => 'X'])->assertForbidden();
})->with([Role::General, Role::Agent, Role::Finance]);

test('single owners have no team to manage', function () {
    $user = User::factory()->for(Account::factory(), 'account')->create();

    $this->actingAs($user)->get(route('team.index'))->assertForbidden();
});

test('admins invite people with a role and branches, receiving a link to send', function () {
    ['account' => $account, 'owner' => $owner, 'ouricuri' => $ouricuri] = teamScenario();

    $this->actingAs($owner)
        ->post(route('team.store'), [
            'name' => 'Bruno Corretor',
            'email' => 'bruno@example.com',
            'role' => 'agent',
            'branch_ids' => [$ouricuri->id],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('team.index'))
        ->assertInertiaFlash('invitation.name', 'Bruno Corretor');

    $member = User::where('email', 'bruno@example.com')->sole();

    expect($member->account_id)->toBe($account->id)
        ->and($member->role)->toBe(Role::Agent)
        ->and($member->hasPendingInvitation())->toBeTrue()
        ->and($member->branches()->pluck('branches.id')->all())->toBe([$ouricuri->id]);
});

test('invitations need a free e-mail and branches for everyone but administrators', function () {
    ['owner' => $owner, 'ouricuri' => $ouricuri] = teamScenario();
    $otherBranch = Branch::factory()->create();

    $this->actingAs($owner)
        ->post(route('team.store'), ['name' => 'Bruno', 'email' => $owner->email, 'role' => 'agent', 'branch_ids' => []])
        ->assertSessionHasErrors(['email', 'branch_ids' => 'Escolha pelo menos uma unidade.']);

    $this->actingAs($owner)
        ->post(route('team.store'), ['name' => 'Bruno', 'email' => 'bruno@example.com', 'role' => 'agent', 'branch_ids' => [$otherBranch->id]])
        ->assertSessionHasErrors('branch_ids.0');

    $this->actingAs($owner)
        ->post(route('team.store'), ['name' => 'Dora', 'email' => 'dora@example.com', 'role' => 'admin', 'branch_ids' => [$ouricuri->id]])
        ->assertSessionHasNoErrors();

    expect(User::where('email', 'dora@example.com')->sole()->branches()->count())->toBe(0);
});

test('invited people create their password through the link and are signed in', function () {
    ['owner' => $owner, 'ouricuri' => $ouricuri] = teamScenario();
    $member = User::factory()->for($owner->account, 'account')->withRole(Role::Agent)->create(['name' => 'Bruno']);
    $token = $member->startInvitation();

    $this->get(route('invitation.show', $token))
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/AcceptInvitation')
            ->where('invitation.name', 'Bruno')
            ->where('invitation.agency', 'Imobiliária Silva')
            ->where('invitation.role', 'Corretor')
        );

    $this->post(route('invitation.accept', $token), [
        'password' => 'nova-senha-123',
        'password_confirmation' => 'nova-senha-123',
    ])->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($member);

    $member->refresh();

    expect($member->hasPendingInvitation())->toBeFalse()
        ->and($member->email_verified_at)->not->toBeNull();

    $this->get(route('invitation.show', $token))
        ->assertInertia(fn (Assert $page) => $page->where('invitation', null));
});

test('expired invitation links no longer work', function () {
    ['owner' => $owner] = teamScenario();
    $member = User::factory()->for($owner->account, 'account')->create();
    $token = $member->startInvitation();

    $this->travel(User::INVITATION_LIFETIME_DAYS + 1)->days();

    $this->get(route('invitation.show', $token))
        ->assertInertia(fn (Assert $page) => $page->where('invitation', null));

    $this->post(route('invitation.accept', $token), [
        'password' => 'nova-senha-123',
        'password_confirmation' => 'nova-senha-123',
    ])->assertNotFound();

    $this->assertGuest();
});

test('admins can renew or cancel invitations nobody accepted yet', function () {
    ['account' => $account, 'owner' => $owner] = teamScenario();
    $invited = User::factory()->for($account, 'account')->invited()->create();
    $active = User::factory()->for($account, 'account')->create();
    $previousToken = $invited->invitation_token;

    $this->actingAs($owner)
        ->post(route('team.invitation', $invited))
        ->assertRedirect(route('team.index'))
        ->assertInertiaFlash('invitation.name', $invited->name);

    expect($invited->refresh()->invitation_token)->not->toBe($previousToken);

    $this->actingAs($owner)->delete(route('team.destroy', $active))->assertForbidden();
    $this->assertModelExists($active);

    $this->actingAs($owner)->delete(route('team.destroy', $invited))->assertRedirect(route('team.index'));
    $this->assertModelMissing($invited);
});

test('admins can change the role and branches of a member', function () {
    ['account' => $account, 'owner' => $owner, 'ouricuri' => $ouricuri, 'araripina' => $araripina] = teamScenario();
    $member = User::factory()->for($account, 'account')->withRole(Role::Agent)->create();
    $member->branches()->attach($ouricuri);

    $this->actingAs($owner)
        ->put(route('team.update', $member), [
            'name' => 'Bruno Silva',
            'role' => 'general',
            'branch_ids' => [$ouricuri->id, $araripina->id],
        ])
        ->assertSessionHasNoErrors();

    $member->refresh();

    expect($member->name)->toBe('Bruno Silva')
        ->and($member->role)->toBe(Role::General)
        ->and($member->branches()->count())->toBe(2);

    $this->actingAs($owner)->put(route('team.update', $member), ['name' => 'Bruno Silva', 'role' => 'admin']);

    expect($member->refresh()->role)->toBe(Role::Admin)
        ->and($member->branches()->count())->toBe(0);
});

test('nobody changes their own access nor the access of the account owner', function () {
    ['account' => $account, 'owner' => $owner] = teamScenario();
    $otherAdmin = User::factory()->for($account, 'account')->create();

    $this->actingAs($otherAdmin)->put(route('team.update', $owner), ['name' => 'X', 'role' => 'agent'])->assertForbidden();
    $this->actingAs($otherAdmin)->patch(route('team.status', $owner))->assertForbidden();
    $this->actingAs($otherAdmin)->patch(route('team.status', $otherAdmin))->assertForbidden();

    expect($owner->refresh()->role)->toBe(Role::Admin)
        ->and($owner->isActive())->toBeTrue()
        ->and($otherAdmin->refresh()->isActive())->toBeTrue();
});

test('members of other accounts are out of reach', function () {
    ['owner' => $owner] = teamScenario();
    $stranger = User::factory()->for(Account::factory()->agency(), 'account')->create();

    $this->actingAs($owner)->patch(route('team.status', $stranger))->assertNotFound();
    $this->actingAs($owner)->delete(route('team.destroy', $stranger))->assertNotFound();
});

test('deactivated members lose their access but keep their history', function () {
    ['account' => $account, 'owner' => $owner] = teamScenario();
    $member = User::factory()->for($account, 'account')->withRole(Role::Agent)->create();

    $this->actingAs($owner)->patch(route('team.status', $member))->assertRedirect(route('team.index'));

    expect($member->refresh()->isActive())->toBeFalse();

    $this->actingAs($member)->get(route('dashboard'))->assertRedirect(route('login'));
    $this->assertGuest();

    $this->post(route('login.store'), ['email' => $member->email, 'password' => 'password'])
        ->assertSessionHasErrors(['email' => 'Seu acesso foi desativado. Fale com o administrador da imobiliária.']);
    $this->assertGuest();

    $this->actingAs($owner)->patch(route('team.status', $member));

    expect($member->refresh()->isActive())->toBeTrue();
});

test('signing in records the last access', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);

    expect($user->refresh()->last_login_at)->not->toBeNull();
});
