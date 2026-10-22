<?php

use App\Enums\MessageTemplate;
use App\Enums\Role;
use App\Models\Account;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('admins and general members rewrite the messages, and a blank one brings the default back', function (Role $role) {
    $account = Account::factory()->agency()->create(['name' => 'Imobiliária Silva']);
    $member = User::factory()->for($account, 'account')->withRole($role)->create();

    $this->actingAs($member)
        ->get(route('message-settings.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Messages')
            ->has('templates', 4)
            ->where('templates.0.key', 'payment_reminder')
            ->where('templates.0.text', MessageTemplate::PaymentReminder->defaultText())
            ->where('templates.0.sample.imobiliaria', 'Imobiliária Silva')
            ->where('hasPixKey', false)
        );

    $this->actingAs($member)
        ->put(route('message-settings.update'), ['message_templates' => [
            'payment_reminder' => "Oi {inquilino}!\r\nVence {vencimento}.",
            'payment_overdue' => '',
            'payout_notice' => MessageTemplate::PayoutNotice->defaultText(),
        ]])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('message-settings.edit'));

    expect($account->refresh()->message_templates)->toBe(['payment_reminder' => "Oi {inquilino}!\nVence {vencimento}."])
        ->and($account->messageTemplate(MessageTemplate::PaymentOverdue))->toBe(MessageTemplate::PaymentOverdue->defaultText());
})->with([
    'admin' => Role::Admin,
    'general' => Role::General,
]);

test('messages may only use their own variables', function () {
    $account = Account::factory()->agency()->create();
    $user = User::factory()->for($account, 'account')->create();

    $this->actingAs($user)
        ->put(route('message-settings.update'), ['message_templates' => [
            'payout_notice' => 'Olá {proprietario}, vence {vencimento}.',
        ]])
        ->assertSessionHasErrors('message_templates.payout_notice');

    expect($account->refresh()->message_templates)->toBeNull();
});

test('single owners have no payout notice to rewrite', function () {
    $user = User::factory()->for(Account::factory(), 'account')->create();

    $this->actingAs($user)
        ->get(route('message-settings.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('templates', 3)
            ->where('templates.2.key', 'adjustment_notice')
        );
});
