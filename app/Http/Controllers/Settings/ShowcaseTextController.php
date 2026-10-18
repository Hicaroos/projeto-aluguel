<?php

namespace App\Http\Controllers\Settings;

use App\Actions\Showcase\PresentShowcaseSite;
use App\Enums\ShowcaseText;
use App\Http\Controllers\Controller;
use App\Http\Middleware\ActAsShowcaseVisitor;
use App\Http\Requests\Settings\ShowcaseTextsRequest;
use App\Models\Account;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The texts of the showcase, rewritten right on its home instead of in a form.
 */
class ShowcaseTextController extends Controller
{
    public function __construct(private PresentShowcaseSite $presentSite) {}

    /**
     * Open the showcase home as visitors see it, with every text ready to be rewritten in place.
     */
    public function edit(Request $request): Response|RedirectResponse
    {
        $account = $this->account($request);

        if (! $account->hasShowcase()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Coloque a vitrine no ar para editar os textos nela.')]);

            return to_route('showcase-settings.edit');
        }

        [$home, $defaultHeadline] = ActAsShowcaseVisitor::during(fn (): array => [
            $this->presentSite->home($account),
            $this->presentSite->defaultHeadline($account),
        ]);

        return Inertia::render('showcase/Home', [
            ...$home,
            'editor' => [
                'defaults' => [...$account->defaultShowcaseTexts(), 'headline' => $defaultHeadline],
                'limits' => [
                    ...collect(ShowcaseText::cases())
                        ->mapWithKeys(fn (ShowcaseText $text): array => [$text->value => $text->maxLength()])
                        ->all(),
                    'headline' => ShowcaseTextsRequest::HEADLINE_MAX_LENGTH,
                ],
            ],
        ])->withViewData(['isShowcase' => true]);
    }

    /**
     * Save the texts, keeping only those that differ from the defaults, so a blank text brings its default back.
     */
    public function update(ShowcaseTextsRequest $request): RedirectResponse
    {
        $account = $this->account($request);
        $headline = $request->validated('showcase_headline');
        $defaultHeadline = ActAsShowcaseVisitor::during(fn (): string => $this->presentSite->defaultHeadline($account));

        $account->update([
            'showcase_headline' => $headline === $defaultHeadline ? null : $headline,
            'showcase_texts' => $account->showcaseTextOverrides((array) $request->validated('showcase_texts', [])),
        ]);

        return to_route('showcase-settings.texts.edit');
    }

    private function account(Request $request): Account
    {
        $account = $request->user()->account;

        abort_if($account === null, 404);

        return $account;
    }
}
