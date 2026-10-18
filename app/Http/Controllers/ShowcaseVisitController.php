<?php

namespace App\Http\Controllers;

use App\Actions\Showcase\RegisterShowcaseVisit;
use App\Http\Requests\ShowcaseVisitRequest;
use App\Models\Account;
use App\Models\Property;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class ShowcaseVisitController extends Controller
{
    /**
     * Take a visit request for a property on the showcase, turning it into a lead in the funnel.
     */
    public function store(ShowcaseVisitRequest $request, Account $showcase, int $property, RegisterShowcaseVisit $registerShowcaseVisit): RedirectResponse
    {
        $requested = Property::query()
            ->where('account_id', $showcase->id)
            ->onShowcase()
            ->findOrFail($property);

        // Bots get the same answer as people, so they don't learn to skip the hidden field.
        if ($request->isFromBot()) {
            return back();
        }

        $visit = $request->visit();
        $isOffered = $visit['purpose'] === 'sale' ? $requested->showcaseSalePrice() !== null : $requested->showcaseRent() !== null;

        if (! $isOffered) {
            throw ValidationException::withMessages([
                'purpose' => __('Este imóvel não está disponível para :purpose.', ['purpose' => $visit['purpose'] === 'sale' ? __('venda') : __('aluguel')]),
            ]);
        }

        $registerShowcaseVisit->handle($requested, $visit);

        return back();
    }
}
