<?php

namespace App\Http\Controllers\Settings;

use App\Enums\PropertyStatus;
use App\Enums\ShowcaseColor;
use App\Enums\ShowcaseText;
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
     * Show the showcase settings: its address, contacts, look and texts, with how many properties are on it.
     */
    public function edit(Request $request): Response
    {
        $account = $this->account($request);
        $properties = fn () => Property::withoutGlobalScope(Property::BRANCH_SCOPE)->withoutGlobalScope(Owner::SELECTION_SCOPE);

        return Inertia::render('settings/Showcase', [
            'showcase' => [
                ...$account->only(['showcase_enabled', 'showcase_whatsapp', 'showcase_headline', 'showcase_shows_address', 'showcase_instagram', 'showcase_facebook', 'showcase_youtube']),
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
            'texts' => $account->showcaseTexts(),
            'defaultTexts' => $account->defaultShowcaseTexts(),
            'textLimits' => collect(ShowcaseText::cases())
                ->mapWithKeys(fn (ShowcaseText $text): array => [$text->value => $text->maxLength()])
                ->all(),
            'isAgency' => $account->isAgency(),
            'publishedCount' => $properties()->onShowcase()->count(),
            'propertiesCount' => $properties()->where('status', '!=', PropertyStatus::Sold)->count(),
        ]);
    }

    /**
     * Update the showcase settings, keeping only the texts that differ from the defaults.
     */
    public function update(ShowcaseSettingsRequest $request): RedirectResponse
    {
        $account = $this->account($request);

        $account->update([
            ...$request->safe()->except('showcase_texts'),
            ...($request->has('showcase_texts')
                ? ['showcase_texts' => $account->showcaseTextOverrides((array) $request->validated('showcase_texts', []))]
                : []),
        ]);

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
