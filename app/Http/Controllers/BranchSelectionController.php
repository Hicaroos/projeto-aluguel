<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Uri;
use Illuminate\Validation\Rule;

class BranchSelectionController extends Controller
{
    /**
     * Pick the branch the whole app shows, or every branch when none is given.
     */
    public function update(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($user->account?->isAgency() ?? false, 404);

        $validated = $request->validate([
            'branch_id' => [
                'nullable',
                'integer',
                Rule::in(Branch::accessibleBy($user)->pluck('id')->all()),
            ],
        ]);

        $branchId = $validated['branch_id'] ?? null;

        if ($branchId === null) {
            $request->session()->forget(User::SELECTED_BRANCH_SESSION_KEY);
        } else {
            $request->session()->put(User::SELECTED_BRANCH_SESSION_KEY, (int) $branchId);
        }

        Branch::forgetVisibleIds();

        return redirect()->to((string) Uri::of(url()->previous())->withoutQuery(['page', 'show', 'action']));
    }
}
