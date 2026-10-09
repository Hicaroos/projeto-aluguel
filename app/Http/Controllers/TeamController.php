<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Http\Requests\TeamMemberRequest;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class TeamController extends Controller
{
    /**
     * Display the people of the agency, their roles, branches and access.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $ownerId = (int) User::where('account_id', $user->account_id)->min('id');

        return Inertia::render('team/Index', [
            'members' => User::where('account_id', $user->account_id)
                ->with('branches:id,name')
                ->orderByRaw('deactivated_at is not null')
                ->orderBy('name')
                ->get()
                ->map(fn (User $member): array => [
                    'id' => $member->id,
                    'name' => $member->name,
                    'email' => $member->email,
                    'role' => $member->role->value,
                    'branches' => $member->branches->map(fn (Branch $branch): array => ['id' => $branch->id, 'name' => $branch->name])->all(),
                    'status' => $this->status($member),
                    'invited_at' => $member->invited_at?->toIso8601String(),
                    'last_login_at' => $member->last_login_at?->toIso8601String(),
                    'is_owner' => $member->id === $ownerId,
                    'is_self' => $member->is($user),
                ])
                ->all(),
            'roles' => collect(Role::cases())->map(fn (Role $role): array => [
                'value' => $role->value,
                'label' => $role->label(),
                'sees_every_branch' => $role->seesEveryBranch(),
            ])->all(),
            'branches' => Branch::query()->active()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Invite a new team member. They get a link to create their password, shown to the admin to
     * send, since the app does not send e-mails yet.
     */
    public function store(TeamMemberRequest $request): RedirectResponse
    {
        $member = DB::transaction(function () use ($request): User {
            $member = $request->user()->account->users()->create([
                'name' => $request->validated('name'),
                'email' => $request->validated('email'),
                'role' => $request->validated('role'),
                'password' => Str::random(64),
            ]);

            $member->branches()->sync($request->branchIds());

            return $member;
        });

        $this->flashInvitationLink($member, $member->startInvitation());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Convite criado. Envie o link para :name.', ['name' => $member->name])]);

        return to_route('team.index');
    }

    /**
     * Update the name, role and branches of a team member.
     */
    public function update(TeamMemberRequest $request, User $member): RedirectResponse
    {
        DB::transaction(function () use ($request, $member): void {
            $member->update([
                'name' => $request->validated('name'),
                'role' => $request->validated('role'),
            ]);

            $member->branches()->sync($member->role->seesEveryBranch() ? [] : $request->branchIds());
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Acesso de :name atualizado.', ['name' => $member->name])]);

        return to_route('team.index');
    }

    /**
     * Take away or give back the access of a team member. Their history stays in the app.
     */
    public function toggleStatus(User $member): RedirectResponse
    {
        Gate::authorize('update', $member);

        $member->update(['deactivated_at' => $member->isActive() ? now() : null]);

        Inertia::flash('toast', ['type' => 'success', 'message' => $member->isActive()
            ? __('Acesso de :name reativado.', ['name' => $member->name])
            : __('Acesso de :name desativado.', ['name' => $member->name])]);

        return to_route('team.index');
    }

    /**
     * Create a new invitation link for a member who has not accepted yet, replacing the old one.
     */
    public function renewInvitation(User $member): RedirectResponse
    {
        Gate::authorize('delete', $member);

        $this->flashInvitationLink($member, $member->startInvitation());

        return to_route('team.index');
    }

    /**
     * Cancel an invitation nobody accepted yet.
     */
    public function destroy(User $member): RedirectResponse
    {
        Gate::authorize('delete', $member);

        $member->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Convite cancelado.')]);

        return to_route('team.index');
    }

    /**
     * Hand the invitation link to the page, so the admin can copy it or send it through WhatsApp.
     */
    private function flashInvitationLink(User $member, string $token): void
    {
        Inertia::flash('invitation', [
            'name' => $member->name,
            'url' => route('invitation.show', $token),
            'expires_in_days' => User::INVITATION_LIFETIME_DAYS,
        ]);
    }

    /**
     * Get where the member stands: still invited, invitation expired, active or deactivated.
     */
    private function status(User $member): string
    {
        $invitationExpired = $member->invited_at?->lt(now()->subDays(User::INVITATION_LIFETIME_DAYS)) ?? false;

        return match (true) {
            ! $member->isActive() => 'inactive',
            $member->hasPendingInvitation() && $invitationExpired => 'expired',
            $member->hasPendingInvitation() => 'pending',
            default => 'active',
        };
    }
}
