<?php

namespace App\Http\Controllers;

use App\Actions\Showcase\RegisterShowcaseListing;
use App\Http\Requests\ShowcaseListingRequest;
use App\Models\Account;
use Illuminate\Http\RedirectResponse;

class ShowcaseListingController extends Controller
{
    /**
     * Take the request of an owner who wants to list a property with the agency, turning it into a lead.
     */
    public function store(ShowcaseListingRequest $request, Account $showcase, RegisterShowcaseListing $registerShowcaseListing): RedirectResponse
    {
        abort_unless($showcase->isAgency(), 404);

        // Bots get the same answer as people, so they don't learn to skip the hidden field.
        if ($request->isFromBot()) {
            return back();
        }

        $registerShowcaseListing->handle($showcase, $request->listing());

        return back();
    }
}
