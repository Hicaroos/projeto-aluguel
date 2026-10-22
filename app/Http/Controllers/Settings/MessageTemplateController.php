<?php

namespace App\Http\Controllers\Settings;

use App\Enums\MessageTemplate;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\MessageTemplatesRequest;
use App\Models\Account;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The WhatsApp messages the team sends from their own phone, each with a default the account may rewrite.
 */
class MessageTemplateController extends Controller
{
    /**
     * Show the messages with their variables and example values to preview them.
     */
    public function edit(Request $request): Response
    {
        $account = $this->account($request);

        return Inertia::render('settings/Messages', [
            'templates' => collect(MessageTemplate::cases())
                ->filter(fn (MessageTemplate $template): bool => $template->isAvailableFor($account))
                ->map(fn (MessageTemplate $template): array => [
                    'key' => $template->value,
                    'label' => $template->label(),
                    'description' => $template->description(),
                    'text' => $account->messageTemplate($template),
                    'default' => $template->defaultText(),
                    'variables' => collect($template->variables($account))
                        ->map(fn (string $label, string $key): array => ['key' => $key, 'label' => $label])
                        ->values(),
                    'sample' => $template->sampleValues($account),
                ])
                ->values(),
            'hasPixKey' => $account->paymentPixKey() !== null,
        ]);
    }

    /**
     * Save the messages, keeping only those that differ from the defaults, so a blank one brings its default back.
     */
    public function update(MessageTemplatesRequest $request): RedirectResponse
    {
        $account = $this->account($request);

        $account->update([
            'message_templates' => $account->messageTemplateOverrides((array) $request->validated('message_templates', [])),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Mensagens salvas.')]);

        return to_route('message-settings.edit');
    }

    private function account(Request $request): Account
    {
        $account = $request->user()->account;

        abort_if($account === null, 404);

        return $account;
    }
}
