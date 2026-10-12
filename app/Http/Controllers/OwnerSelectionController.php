<?php

namespace App\Http\Controllers;

use App\Models\Owner;
use App\Models\User;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Uri;

class OwnerSelectionController extends Controller
{
    /**
     * List the owners the user may pick in the owner selector, loaded when the selector opens.
     */
    public function index(): JsonResponse
    {
        return response()->json(
            Owner::query()
                ->orderBy('name')
                ->get(['id', 'name', 'cpf_cnpj']),
        );
    }

    /**
     * Pick the owner whose records the whole app shows, or every owner when none is given.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'owner_id' => [
                'nullable',
                'integer',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! Owner::query()->whereKey($value)->exists()) {
                        $fail(__('Proprietário não encontrado.'));
                    }
                },
            ],
        ]);

        $ownerId = $validated['owner_id'] ?? null;

        if ($ownerId === null) {
            $request->session()->forget(User::SELECTED_OWNER_SESSION_KEY);
        } else {
            $request->session()->put(User::SELECTED_OWNER_SESSION_KEY, (int) $ownerId);
        }

        Owner::forgetSelectedId();

        return redirect()->to((string) Uri::of(url()->previous())->withoutQuery(['page', 'show', 'action']));
    }
}
