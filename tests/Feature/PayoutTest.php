<?php

use App\Actions\Payouts\RegisterOwnerPayout;
use App\Enums\GuaranteeType;
use App\Enums\LedgerEntryType;
use App\Enums\PaymentType;
use App\Enums\Role;
use App\Models\Account;
use App\Models\Branch;
use App\Models\Lease;
use App\Models\Owner;
use App\Models\OwnerLedgerEntry;
use App\Models\OwnerPayout;
use App\Models\Payment;
use App\Models\Property;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo('2026-03-20 09:00:00');
});

/**
 * An agency with an owner whose account has R$ 900 to pay out and R$ 450 waiting for its date.
 *
 * @return array{account: Account, user: User, branch: Branch, owner: Owner, property: Property}
 */
function payoutScenario(): array
{
    $account = Account::factory()->agency()->create();
    $branch = Branch::factory()->for($account, 'account')->create();
    $owner = Owner::factory()->for($account, 'account')->create(['name' => 'Maria', 'pix_key' => 'maria@pix.com']);
    $property = Property::factory()->for($account, 'account')->for($owner, 'owner')->create(['branch_id' => $branch->id, 'street' => 'Rua das Flores', 'number' => '10']);

    $entry = fn (string $type, float $amount, string $occurredOn, ?string $availableOn = null) => OwnerLedgerEntry::factory()->for($owner)->create([
        'account_id' => $account->id,
        'property_id' => $property->id,
        'type' => $type,
        'description' => LedgerEntryType::from($type)->label(),
        'amount' => $amount,
        'occurred_on' => $occurredOn,
        'available_on' => $availableOn ?? $occurredOn,
    ]);

    $entry('rent', 1000, '2026-02-10', '2026-02-15');
    $entry('admin_fee', -100, '2026-02-10', '2026-02-15');
    $entry('rent', 500, '2026-03-18', '2026-03-23');
    $entry('admin_fee', -50, '2026-03-18', '2026-03-23');

    return [
        'account' => $account,
        'user' => User::factory()->for($account, 'account')->create(),
        'branch' => $branch,
        'owner' => $owner,
        'property' => $property,
    ];
}

test('the payouts page lists each owner with what may be paid out and the totals', function () {
    ['user' => $user, 'owner' => $owner] = payoutScenario();

    $this->actingAs($user)
        ->get(route('payouts.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('payouts/Index')
            ->has('owners', 1)
            ->where('owners.0.id', $owner->id)
            ->where('owners.0.available', 900)
            ->where('owners.0.pending', 450)
            ->where('totals.available', 900)
            ->where('totals.pending', 450)
            ->where('totals.fees', 50)
        );
});

test('the payouts page credits the guaranteed rent that came due', function () {
    ['account' => $account, 'user' => $user, 'owner' => $owner, 'property' => $property] = payoutScenario();
    $account->update(['payout_mode' => 'guaranteed', 'payout_days' => 0, 'admin_fee_percent' => 10]);
    $lease = Lease::factory()->for($account, 'account')->for($property, 'property')->create(['start_date' => '2025-01-01', 'amount' => 2000, 'due_day' => 10]);
    Payment::factory()->for($lease)->create(['account_id' => $account->id, 'type' => PaymentType::Rent, 'reference_month' => '2026-03-01', 'due_date' => '2026-03-10', 'amount' => 2000]);

    $this->actingAs($user)
        ->get(route('payouts.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('owners.0.available', 900 + 1800)
            ->where('totals.advanced', 2000)
        );
});

test('the statement shows the month entries with the balance after each one', function () {
    ['account' => $account, 'user' => $user, 'owner' => $owner, 'property' => $property] = payoutScenario();
    Lease::factory()->for($account, 'account')->for($property, 'property')->create([
        'guarantee_type' => GuaranteeType::Deposit,
        'deposit_amount' => 3000,
    ]);

    $this->actingAs($user)
        ->get(route('payouts.show', ['owner' => $owner->id, 'month' => '2026-03']))
        ->assertInertia(fn (Assert $page) => $page
            ->component('payouts/Show')
            ->where('month', '2026-03')
            ->where('statement.opening_balance', 900)
            ->where('statement.closing_balance', 1350)
            ->where('statement.available', 900)
            ->where('statement.pending', 450)
            ->has('statement.entries', 2)
            ->where('statement.entries.0.balance', 1400)
            ->where('statement.entries.0.is_pending', true)
            ->where('statement.entries.1.balance', 1350)
            ->where('statement.entries.0.property.street', 'Rua das Flores')
            ->has('statement.deposits', 1)
            ->where('statement.deposits.0.amount', '3000.00')
        );
});

test('owners are paid at most what is already theirs to receive', function () {
    ['user' => $user, 'owner' => $owner] = payoutScenario();

    $this->actingAs($user)
        ->post(route('payouts.store', $owner), ['amount' => '1000', 'paid_on' => '2026-03-20', 'payment_method' => 'pix'])
        ->assertSessionHasErrors('amount');

    $this->actingAs($user)
        ->post(route('payouts.store', $owner), ['amount' => '900', 'paid_on' => '2026-03-21', 'payment_method' => 'pix'])
        ->assertSessionHasErrors('paid_on');

    $this->actingAs($user)
        ->post(route('payouts.store', $owner), ['amount' => '900', 'paid_on' => '2026-03-20', 'payment_method' => 'pix', 'notes' => 'Pix agendado'])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('payouts.show', ['owner' => $owner->id, 'month' => '2026-03']));

    $payout = OwnerPayout::sole();
    $entry = OwnerLedgerEntry::where('type', LedgerEntryType::Payout)->sole();

    expect($payout->amount)->toBe('900.00')
        ->and($payout->user_id)->toBe($user->id)
        ->and($entry->amount)->toBe('-900.00')
        ->and($entry->description)->toBe('Repasse via Pix');

    $this->actingAs($user)
        ->get(route('payouts.index'))
        ->assertInertia(fn (Assert $page) => $page->where('owners.0.available', 0)->where('owners.0.last_payout_on', '2026-03-20'));
});

test('a payout checked against a balance another one already took is refused, as on a double click', function () {
    ['user' => $user, 'owner' => $owner] = payoutScenario();
    $payout = ['amount' => '900', 'paid_on' => '2026-03-20', 'payment_method' => 'pix'];

    $this->actingAs($user)->post(route('payouts.store', $owner), $payout)->assertSessionHasNoErrors();

    // The second click passed the form validation before the first was saved.
    expect(fn () => app(RegisterOwnerPayout::class)->handle($owner, $payout, $user))
        ->toThrow(ValidationException::class);

    expect(OwnerPayout::count())->toBe(1)
        ->and(OwnerLedgerEntry::where('type', LedgerEntryType::Payout)->count())->toBe(1);
});

test('a payout registered by mistake can be removed, giving the amount back', function () {
    ['user' => $user, 'owner' => $owner] = payoutScenario();
    $this->actingAs($user)->post(route('payouts.store', $owner), ['amount' => '500', 'paid_on' => '2026-03-20']);

    $this->actingAs($user)
        ->delete(route('owner-payouts.destroy', OwnerPayout::sole()))
        ->assertSessionHasNoErrors();

    expect(OwnerPayout::count())->toBe(0)
        ->and(OwnerLedgerEntry::where('type', LedgerEntryType::Payout)->count())->toBe(0);
});

test('adjustments are entered by hand and only they can be removed', function () {
    ['user' => $user, 'owner' => $owner, 'property' => $property] = payoutScenario();
    $otherProperty = Property::factory()->create();

    $this->actingAs($user)
        ->post(route('payouts.adjustments.store', $owner), ['direction' => 'debit', 'amount' => '80', 'description' => 'Chaveiro', 'occurred_on' => '2026-03-19', 'property_id' => $property->id])
        ->assertSessionHasNoErrors();

    $this->actingAs($user)
        ->post(route('payouts.adjustments.store', $owner), ['direction' => 'credit', 'amount' => '30', 'description' => '', 'occurred_on' => '2026-03-19', 'property_id' => $otherProperty->id])
        ->assertSessionHasErrors(['description', 'property_id']);

    $adjustment = OwnerLedgerEntry::where('type', LedgerEntryType::Adjustment)->sole();

    expect($adjustment->amount)->toBe('-80.00')
        ->and($adjustment->user_id)->toBe($user->id);

    $this->actingAs($user)
        ->delete(route('ledger-entries.destroy', OwnerLedgerEntry::where('type', LedgerEntryType::Rent)->first()))
        ->assertForbidden();

    $this->actingAs($user)
        ->delete(route('ledger-entries.destroy', $adjustment))
        ->assertSessionHasNoErrors();

    $this->assertModelMissing($adjustment);
});

test('the statement can be downloaded as a PDF', function () {
    ['user' => $user, 'owner' => $owner] = payoutScenario();

    $this->actingAs($user)
        ->get(route('payouts.pdf', ['owner' => $owner->id, 'month' => '2026-03']))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

test('only agency members who handle the money reach the payouts', function () {
    ['account' => $account, 'branch' => $branch, 'owner' => $owner] = payoutScenario();
    $agent = User::factory()->for($account, 'account')->withRole(Role::Agent)->create();
    $agent->branches()->attach($branch);
    $finance = User::factory()->for($account, 'account')->withRole(Role::Finance)->create();
    $finance->branches()->attach($branch);
    $stranger = User::factory()->for(Account::factory()->agency(), 'account')->create();
    $singleOwner = User::factory()->for(Account::factory(), 'account')->create();

    $this->actingAs($agent)->get(route('payouts.index'))->assertForbidden();
    $this->actingAs($finance)->get(route('payouts.show', $owner))->assertOk();
    $this->actingAs($stranger)->get(route('payouts.show', $owner))->assertNotFound();
    $this->actingAs($singleOwner)->get(route('payouts.index'))->assertNotFound();
});

test('the proof of transfer goes along with the payout and leaves with it', function () {
    Storage::fake(OwnerPayout::DISK);
    ['user' => $user, 'owner' => $owner] = payoutScenario();

    $this->actingAs($user)
        ->post(route('payouts.store', $owner), [
            'amount' => '900',
            'paid_on' => '2026-03-20',
            'payment_method' => 'pix',
            'receipt' => UploadedFile::fake()->create('comprovante-pix.pdf', 120, 'application/pdf'),
        ])
        ->assertSessionHasNoErrors();

    $payout = OwnerPayout::sole();

    expect($payout->receipt_name)->toBe('comprovante-pix.pdf');
    Storage::disk(OwnerPayout::DISK)->assertExists($payout->receipt_path);

    $this->actingAs($user)
        ->get(route('payouts.show', ['owner' => $owner->id, 'month' => '2026-03']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('statement.entries.2.owner_payout_id', $payout->id)
            ->where('statement.entries.2.payout_receipt_name', 'comprovante-pix.pdf')
            ->where('statement.entries.0.payout_receipt_name', null)
        );

    $this->actingAs($user)->get(route('owner-payouts.receipt.show', $payout))->assertOk();

    $this->actingAs($user)->delete(route('owner-payouts.destroy', $payout));

    Storage::disk(OwnerPayout::DISK)->assertMissing($payout->receipt_path);
});

test('the proof of transfer can be attached later, replaced and removed', function () {
    Storage::fake(OwnerPayout::DISK);
    ['user' => $user, 'owner' => $owner] = payoutScenario();
    $this->actingAs($user)->post(route('payouts.store', $owner), ['amount' => '500', 'paid_on' => '2026-03-20']);
    $payout = OwnerPayout::sole();

    $this->actingAs($user)->get(route('owner-payouts.receipt.show', $payout))->assertNotFound();

    $this->actingAs($user)
        ->post(route('owner-payouts.receipt.store', $payout), ['receipt' => UploadedFile::fake()->create('planilha.xlsx', 50, 'application/vnd.ms-excel')])
        ->assertSessionHasErrors(['receipt' => 'Envie um PDF ou uma imagem (JPG, PNG ou WebP).']);

    $this->actingAs($user)
        ->post(route('owner-payouts.receipt.store', $payout), ['receipt' => UploadedFile::fake()->create('print.png', 90, 'image/png')])
        ->assertSessionHasNoErrors();

    $firstPath = $payout->fresh()->receipt_path;

    $this->actingAs($user)
        ->post(route('owner-payouts.receipt.store', $payout), ['receipt' => UploadedFile::fake()->create('ted.pdf', 80, 'application/pdf')])
        ->assertSessionHasNoErrors();

    expect($payout->fresh()->receipt_name)->toBe('ted.pdf');
    Storage::disk(OwnerPayout::DISK)->assertMissing($firstPath);
    Storage::disk(OwnerPayout::DISK)->assertExists($payout->fresh()->receipt_path);

    $this->actingAs($user)->delete(route('owner-payouts.receipt.destroy', $payout))->assertSessionHasNoErrors();

    expect($payout->fresh()->receipt_path)->toBeNull()
        ->and(OwnerPayout::count())->toBe(1);
});

test('proofs of transfer of other accounts are out of reach', function () {
    Storage::fake(OwnerPayout::DISK);
    ['user' => $user, 'owner' => $owner] = payoutScenario();
    $this->actingAs($user)->post(route('payouts.store', $owner), [
        'amount' => '500',
        'paid_on' => '2026-03-20',
        'receipt' => UploadedFile::fake()->create('comprovante.pdf', 50, 'application/pdf'),
    ]);
    $payout = OwnerPayout::sole();
    $stranger = User::factory()->for(Account::factory()->agency(), 'account')->create();

    $this->actingAs($stranger)->get(route('owner-payouts.receipt.show', $payout))->assertNotFound();
    $this->actingAs($stranger)->delete(route('owner-payouts.receipt.destroy', $payout))->assertNotFound();

    expect($payout->fresh()->receipt_path)->not->toBeNull();
});
