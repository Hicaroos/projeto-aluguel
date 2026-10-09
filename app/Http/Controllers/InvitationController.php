<?php

namespace App\Http\Controllers;

use App\Concerns\PasswordValidationRules;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class InvitationController extends Controller
{
    use PasswordValidationRules;

    /**
     * Show the page where an invited team member creates their password.
     */
    public function show(string $token): Response
    {
        $member = User::withValidInvitation($token)->with('account:id,name')->first();

        return Inertia::render('auth/AcceptInvitation', [
            'invitation' => $member === null ? null : [
                'token' => $token,
                'name' => $member->name,
                'email' => $member->email,
                'agency' => $member->account?->name,
                'role' => $member->role->label(),
            ],
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }

    /**
     * Create the password of the invited member and sign them in.
     */
    public function accept(Request $request, string $token): RedirectResponse
    {
        $member = User::withValidInvitation($token)->first();

        abort_if($member === null, 404);

        $request->validate(['password' => $this->passwordRules()]);

        $member->forceFill([
            'password' => $request->string('password')->toString(),
            'invitation_token' => null,
            'email_verified_at' => $member->email_verified_at ?? now(),
        ])->save();

        Auth::guard('web')->login($member);
        $request->session()->regenerate();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Boas-vindas à equipe, :name!', ['name' => $member->name])]);

        return to_route('dashboard');
    }
}
