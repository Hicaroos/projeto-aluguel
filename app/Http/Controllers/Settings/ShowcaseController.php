<?php

namespace App\Http\Controllers\Settings;

use App\Enums\PropertyStatus;
use App\Enums\ShowcaseColor;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ShowcaseSettingsRequest;
use App\Models\Account;
use App\Models\Owner;
use App\Models\Property;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ShowcaseController extends Controller
{
    /**
     * Show the showcase settings: its address, contact and look, with how many properties are on it.
     */
    public function edit(Request $request): Response
    {
        $account = $this->account($request);
        $properties = fn () => Property::withoutGlobalScope(Property::BRANCH_SCOPE)->withoutGlobalScope(Owner::SELECTION_SCOPE);

        return Inertia::render('settings/Showcase', [
            'showcase' => [
                ...$account->only(['showcase_enabled', 'showcase_whatsapp', 'showcase_headline']),
                'showcase_slug' => $account->showcase_slug ?? Str::slug($account->name),
                'showcase_color' => $account->showcaseColor()->value,
                'url' => $account->hasShowcase() ? route('showcase.home', $account->showcase_slug) : null,
            ],
            'baseUrl' => rtrim(url('/'), '/').'/',
            'colors' => array_map(fn (ShowcaseColor $color): array => [
                'value' => $color->value,
                'label' => $color->label(),
                'hex' => $color->hex(),
            ], ShowcaseColor::cases()),
            'publishedCount' => $properties()->onShowcase()->count(),
            'propertiesCount' => $properties()->where('status', '!=', PropertyStatus::Sold)->count(),
        ]);
    }

    /**
     * Update the showcase settings.
     */
    public function update(ShowcaseSettingsRequest $request): RedirectResponse
    {
        $this->account($request)->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Vitrine atualizada.')]);

        return to_route('showcase-settings.edit');
    }

    private function account(Request $request): Account
    {
        $account = $request->user()->account;

        abort_if($account === null, 404);

        return $account;
    }
}
