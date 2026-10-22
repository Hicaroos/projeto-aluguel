<?php

use App\Enums\MessageTemplate;
use App\Models\Account;
use App\Models\Lease;
use App\Models\LeaseAdjustment;
use App\Models\Owner;
use App\Models\OwnerLedgerEntry;
use App\Models\OwnerPayout;
use App\Models\Payment;
use App\Models\Property;
use App\Models\SentMessage;
use App\Models\Tenant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * An agency with a R$ 1.000 lease due on the 10th, whose tenant has a WhatsApp number.
 *
 * @return array{account: Account, user: User, lease: Lease}
 */
function whatsAppScenario(array $accountAttributes = []): array
{
    $account = Account::factory()->agency()->create(['name' => 'Imobiliária Silva', ...$accountAttributes]);
    $owner = Owner::factory()->for($account, 'account')->create();

    return [
        'account' => $account,
        'user' => User::factory()->for($account, 'account')->create(['name' => 'Ana']),
        'lease' => Lease::factory()->for($account, 'account')->create([
            'property_id' => Property::factory()->for($account, 'account')->for($owner, 'owner')->create(['street' => 'Rua das Flores', 'number' => '120', 'complement' => null])->id,
            'tenant_id' => Tenant::factory()->for($account, 'account')->create(['name' => 'Maria Souza', 'phone' => '87999990000'])->id,
            'start_date' => '2026-01-01',
            'end_date' => '2028-06-30',
            'due_day' => 10,
            'amount' => 1000,
        ]),
    ];
}

test('open payments carry a reminder for the tenant until they are due', function () {
    $this->travelTo('2026-10-05 09:00:00');
    ['user' => $user] = whatsAppScenario(['pix_key' => '12.345.678/0001-95']);

    $this->actingAs($user)
        ->get(route('payments.index', ['month' => '2026-10']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('payments.data.0.whatsapp.template', 'payment_reminder')
            ->where('payments.data.0.whatsapp.action_label', 'Lembrar vencimento')
            ->where('payments.data.0.whatsapp.phone', '5587999990000')
            ->where('payments.data.0.whatsapp.text', fn (string $text) => str_contains($text, 'Olá, Maria Souza!')
                && str_contains($text, '*aluguel de outubro/2026* do imóvel Rua das Flores, 120')
                && str_contains($text, '1.000,00')
                && str_contains($text, 'Vencimento: 10/10/2026')
                && str_contains($text, 'Chave Pix: 12.345.678/0001-95')
                && str_ends_with($text, 'Imobiliária Silva'))
            ->where('payments.data.0.whatsapp.url', fn (string $url) => str_starts_with($url, 'https://wa.me/5587999990000?text=Ol%C3%A1%2C%20Maria'))
            ->where('payments.data.0.whatsapp.last_sent', null)
        );
});

test('overdue payments carry a collection with the late charges, and paid ones carry nothing', function () {
    $this->travelTo('2026-10-15 09:00:00');
    ['user' => $user, 'lease' => $lease] = whatsAppScenario();

    $this->actingAs($user)
        ->get(route('payments.index', ['month' => '2026-10']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('payments.data.0.whatsapp.template', 'payment_overdue')
            ->where('payments.data.0.whatsapp.action_label', 'Cobrar atraso')
            ->where('payments.data.0.whatsapp.text', fn (string $text) => str_contains($text, 'venceu em 10/10/2026 (5 dias de atraso)')
                && str_contains($text, '1.101,67')
                && ! str_contains($text, 'Chave Pix'))
        );

    $lease->payments()->whereDate('due_date', '2026-10-10')->update(['status' => 'paid']);

    $this->actingAs($user)
        ->get(route('payments.index', ['month' => '2026-10']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('payments.data.0.status', 'paid')
            ->where('payments.data.0.whatsapp', null)
        );
});

test('messages follow the text the account rewrote, and single owners charge to their own Pix key', function () {
    $this->travelTo('2026-10-05 09:00:00');
    $account = Account::factory()->create(['message_templates' => ['payment_reminder' => "Oi {inquilino}, vence {vencimento}.\nPix: {chave_pix}"]]);
    $owner = Owner::factory()->for($account, 'account')->create(['pix_key' => 'dono@pix.com']);
    $user = User::factory()->for($account, 'account')->create();
    Lease::factory()->for($account, 'account')->create([
        'property_id' => Property::factory()->for($account, 'account')->for($owner, 'owner')->create()->id,
        'tenant_id' => Tenant::factory()->for($account, 'account')->create(['name' => 'Maria', 'phone' => '87999990000'])->id,
        'start_date' => '2026-01-01',
        'end_date' => '2028-06-30',
        'due_day' => 10,
    ]);

    $this->actingAs($user)
        ->get(route('payments.index', ['month' => '2026-10']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('payments.data.0.whatsapp.text', "Oi Maria, vence 10/10/2026.\nPix: dono@pix.com")
        );
});

test('tenants without a phone WhatsApp can call get no link', function () {
    $this->travelTo('2026-10-05 09:00:00');
    ['user' => $user, 'lease' => $lease] = whatsAppScenario();
    $lease->tenant->update(['phone' => null]);

    $this->actingAs($user)
        ->get(route('payments.index', ['month' => '2026-10']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('payments.data.0.whatsapp.phone', null)
            ->where('payments.data.0.whatsapp.url', null)
        );

    $this->actingAs($user)
        ->post(route('payments.whatsapp', $lease->payments()->first()))
        ->assertRedirect();

    expect(SentMessage::count())->toBe(0);
});

test('opening a payment message records who sent it and when', function () {
    $this->travelTo('2026-10-15 09:00:00');
    ['user' => $user, 'lease' => $lease] = whatsAppScenario();

    $this->actingAs($user)->get(route('payments.index', ['month' => '2026-10']));
    $payment = $lease->payments()->whereMonth('due_date', 10)->sole();

    $this->actingAs($user)
        ->from(route('payments.index'))
        ->post(route('payments.whatsapp', $payment))
        ->assertRedirect(route('payments.index'));

    $message = SentMessage::sole();

    expect($message->template)->toBe(MessageTemplate::PaymentOverdue)
        ->and($message->user_id)->toBe($user->id)
        ->and($message->account_id)->toBe($user->account_id)
        ->and($message->phone)->toBe('5587999990000')
        ->and($message->messageable->is($payment))->toBeTrue()
        ->and($message->body)->toContain('Maria Souza');

    $this->actingAs($user)
        ->get(route('payments.index', ['month' => '2026-10']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('payments.data.0.whatsapp.last_sent.user', 'Ana')
            ->where('payments.data.0.whatsapp.last_sent.label', 'Cobrança de atraso')
        );
});

test('payment messages of another account cannot be recorded', function () {
    ['lease' => $lease] = whatsAppScenario();
    $payment = Payment::factory()->for($lease)->create();
    $stranger = User::factory()->for(Account::factory(), 'account')->create();

    $this->actingAs($stranger)->post(route('payments.whatsapp', $payment))->assertNotFound();

    expect(SentMessage::withoutGlobalScopes()->count())->toBe(0);
});

test('the owner statement carries the notice of each payout, and sending it is recorded', function () {
    $this->travelTo('2026-10-15 09:00:00');
    ['account' => $account, 'user' => $user] = whatsAppScenario();
    $owner = Owner::factory()->for($account, 'account')->create(['name' => 'João Lima', 'phone' => '(87) 98888-7777']);
    $payout = OwnerPayout::factory()->for($owner)->create(['amount' => 900, 'paid_on' => '2026-10-15']);
    OwnerLedgerEntry::factory()->for($owner)->create([
        'account_id' => $account->id,
        'owner_payout_id' => $payout->id,
        'type' => 'payout',
        'description' => 'Repasse',
        'amount' => -900,
        'occurred_on' => '2026-10-15',
        'available_on' => '2026-10-15',
    ]);

    $this->actingAs($user)
        ->get(route('payouts.show', ['owner' => $owner, 'month' => '2026-10']))
        ->assertInertia(fn (Assert $page) => $page
            ->where("payoutMessages.{$payout->id}.template", 'payout_notice')
            ->where("payoutMessages.{$payout->id}.phone", '5587988887777')
            ->where("payoutMessages.{$payout->id}.text", fn (string $text) => str_contains($text, 'Olá, João Lima!')
                && str_contains($text, '900,00 foi feito em 15/10/2026'))
        );

    $this->actingAs($user)->post(route('owner-payouts.whatsapp', $payout))->assertRedirect();

    expect(SentMessage::sole()->template)->toBe(MessageTemplate::PayoutNotice);
});

test('the lease details carry the notice of the last adjustment, and sending it is recorded', function () {
    ['user' => $user, 'lease' => $lease] = whatsAppScenario();
    LeaseAdjustment::factory()->for($lease)->create(['effective_on' => '2027-01-01', 'percent' => 4.5, 'previous_amount' => 1000, 'new_amount' => 1045]);
    $latest = LeaseAdjustment::factory()->for($lease)->create(['effective_on' => '2028-01-01', 'percent' => 3, 'previous_amount' => 1045, 'new_amount' => 1076.35]);

    $this->actingAs($user)
        ->get(route('leases.index', ['show' => $lease->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->missing('selected.adjustments.0.whatsapp')
            ->where('selected.adjustments.1.whatsapp.template', 'adjustment_notice')
            ->where('selected.adjustments.1.whatsapp.text', fn (string $text) => str_contains($text, 'reajustado em 3,00% (IGP-M/FGV)')
                && str_contains($text, 'A partir de 01/01/2028')
                && str_contains($text, '1.076,35'))
        );

    $this->actingAs($user)->post(route('lease-adjustments.whatsapp', $latest))->assertRedirect();

    expect(SentMessage::sole()->messageable->is($latest))->toBeTrue();
});

test('adjustment messages of another account cannot be recorded', function () {
    ['lease' => $lease] = whatsAppScenario();
    $adjustment = LeaseAdjustment::factory()->for($lease)->create();
    $stranger = User::factory()->for(Account::factory(), 'account')->create();

    $this->actingAs($stranger)->post(route('lease-adjustments.whatsapp', $adjustment))->assertNotFound();
});
