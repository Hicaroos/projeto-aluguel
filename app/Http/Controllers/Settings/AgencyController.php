<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\AgencyUpdateRequest;
use App\Models\Account;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AgencyController extends Controller
{
    /**
     * Show the agency details used in contracts, receipts and across the app.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/Agency', [
            'agency' => $this->agency($request),
        ]);
    }

    /**
     * Update the agency details.
     */
    public function update(AgencyUpdateRequest $request): RedirectResponse
    {
        $this->agency($request)->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Dados da imobiliária atualizados.')]);

        return to_route('agency.edit');
    }

    /**
     * Replace the agency logo. The browser resizes it before uploading.
     */
    public function updateLogo(Request $request): RedirectResponse
    {
        $request->validate([
            'logo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ], attributes: ['logo' => __('logo')]);

        $agency = $this->agency($request);
        $previousPath = $agency->logo_path;

        /** @var UploadedFile $logo */
        $logo = $request->file('logo');

        $agency->update(['logo_path' => $logo->store("accounts/{$agency->id}", Account::LOGO_DISK)]);

        if ($previousPath !== null) {
            Storage::disk(Account::LOGO_DISK)->delete($previousPath);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Logo atualizada.')]);

        return to_route('agency.edit');
    }

    /**
     * Remove the agency logo.
     */
    public function destroyLogo(Request $request): RedirectResponse
    {
        $agency = $this->agency($request);

        if ($agency->logo_path !== null) {
            Storage::disk(Account::LOGO_DISK)->delete($agency->logo_path);
            $agency->update(['logo_path' => null]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Logo removida.')]);

        return to_route('agency.edit');
    }

    /**
     * Serve the agency logo to the people of the agency.
     */
    public function logo(Request $request): StreamedResponse
    {
        $path = $this->agency($request)->logo_path;

        abort_if($path === null || ! Storage::disk(Account::LOGO_DISK)->exists($path), 404);

        return Storage::disk(Account::LOGO_DISK)->response($path, headers: ['Cache-Control' => 'private, max-age=31536000']);
    }

    /**
     * Get the account of the signed in agency. Single owners keep their details elsewhere.
     */
    private function agency(Request $request): Account
    {
        $account = $request->user()->account;

        abort_unless($account?->isAgency() ?? false, 404);

        return $account;
    }
}
