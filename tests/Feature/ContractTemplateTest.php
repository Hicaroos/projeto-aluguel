<?php

use App\Models\Account;
use App\Models\ContractTemplate;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @return array{account: Account, user: User}
 */
function contractTemplateScenario(): array
{
    $account = Account::factory()->create();

    return [
        'account' => $account,
        'user' => User::factory()->for($account, 'account')->create(),
    ];
}

test('index creates the standard template of each kind once for the account', function () {
    ['account' => $account, 'user' => $user] = contractTemplateScenario();
    ContractTemplate::factory()->create();

    $this->actingAs($user)
        ->get(route('contract-templates.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('contract-templates/Index')
            ->has('templates', 3)
            ->has('kinds', 3)
            ->where('templates', fn ($templates) => collect($templates)->every(fn (array $template): bool => $template['is_default']))
        );

    $this->actingAs($user)->get(route('contract-templates.index'));

    $templates = $account->contractTemplates()->get()->keyBy(fn (ContractTemplate $template): string => $template->kind->value);

    expect($templates)->toHaveCount(3)
        ->and($templates['lease']->name)->toBe('Contrato residencial padrão')
        ->and($templates['lease']->body)->toContain('data-variable="tenant.qualification"')
        ->and($templates['sale_contract']->body)->toContain('data-variable="buyer.qualification"')
        ->and($templates['down_payment_receipt']->body)->toContain('data-variable="sale.down_payment"');
});

test('templates only use the variables of their kind', function () {
    ['user' => $user] = contractTemplateScenario();

    $this->actingAs($user)
        ->post(route('contract-templates.store'), [
            'kind' => 'down_payment_receipt',
            'name' => 'Recibo',
            'body' => '<p>Recebi de <span data-variable="tenant.name"></span>.</p>',
        ])
        ->assertSessionHasErrors(['body' => 'A variável "Nome do locatário" não pode ser usada neste tipo de modelo.']);

    $this->actingAs($user)
        ->post(route('contract-templates.store'), [
            'kind' => 'down_payment_receipt',
            'name' => 'Recibo curto',
            'body' => '<p>Recebi de <span data-variable="buyer.name"></span> o sinal de <span data-variable="sale.down_payment"></span>.</p>',
        ])
        ->assertSessionHasNoErrors();

    expect(ContractTemplate::where('name', 'Recibo curto')->sole()->kind->value)->toBe('down_payment_receipt');
});

test('the editor receives the variables catalog', function () {
    ['account' => $account, 'user' => $user] = contractTemplateScenario();
    $template = ContractTemplate::factory()->for($account, 'account')->create();

    $this->actingAs($user)
        ->get(route('contract-templates.edit', $template))
        ->assertInertia(fn (Assert $page) => $page
            ->component('contract-templates/Edit')
            ->where('template.id', $template->id)
            ->where('variables.0.key', 'owner.qualification')
            ->has('variables.0.label')
            ->has('variables.0.group')
        );

    $this->actingAs($user)
        ->get(route('contract-templates.create'))
        ->assertInertia(fn (Assert $page) => $page->where('template', null));
});

test('a template is stored sanitized and the first one becomes the default', function () {
    ['account' => $account, 'user' => $user] = contractTemplateScenario();

    $this->actingAs($user)
        ->post(route('contract-templates.store'), [
            'name' => 'Comercial',
            'body' => '<p style="color:red" onclick="alert(1)">Locatário <span data-variable="tenant.name" class="x">Nome</span><script>alert(1)</script><a href="https://x.test">link</a></p>',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('contract-templates.index'));

    $template = $account->contractTemplates()->sole();

    expect($template->is_default)->toBeTrue()
        ->and($template->body)->toContain('<span data-variable="tenant.name">')
        ->and($template->body)->not->toContain('script')
        ->and($template->body)->not->toContain('onclick')
        ->and($template->body)->not->toContain('style')
        ->and($template->body)->not->toContain('href');

    $this->actingAs($user)
        ->post(route('contract-templates.store'), ['name' => 'Outro', 'body' => '<p>Texto</p>'])
        ->assertSessionHasNoErrors();

    expect($account->contractTemplates()->where('name', 'Outro')->sole()->is_default)->toBeFalse();
});

test('a template rejects unknown variables and empty text', function () {
    ['user' => $user] = contractTemplateScenario();

    $this->actingAs($user)
        ->post(route('contract-templates.store'), ['name' => 'X', 'body' => '<p><span data-variable="tenant.salary"></span></p>'])
        ->assertSessionHasErrors('body');

    $this->actingAs($user)
        ->post(route('contract-templates.store'), ['name' => 'X', 'body' => '<script>alert(1)</script>'])
        ->assertSessionHasErrors('body');

    expect(ContractTemplate::count())->toBe(0);
});

test('a template can be updated and made the default', function () {
    ['account' => $account, 'user' => $user] = contractTemplateScenario();
    $default = ContractTemplate::factory()->default()->for($account, 'account')->create();
    $template = ContractTemplate::factory()->for($account, 'account')->create();

    $this->actingAs($user)
        ->put(route('contract-templates.update', $template), ['name' => 'Renomeado', 'body' => '<p>Novo texto</p>'])
        ->assertSessionHasNoErrors();

    $this->actingAs($user)->patch(route('contract-templates.default', $template));

    expect($template->fresh()->name)->toBe('Renomeado')
        ->and($template->fresh()->body)->toBe('<p>Novo texto</p>')
        ->and($template->fresh()->is_default)->toBeTrue()
        ->and($default->fresh()->is_default)->toBeFalse();
});

test('the last template cannot be deleted and deleting the default promotes another one', function () {
    ['account' => $account, 'user' => $user] = contractTemplateScenario();
    $default = ContractTemplate::factory()->default()->for($account, 'account')->create();

    $this->actingAs($user)->delete(route('contract-templates.destroy', $default));

    expect($default->fresh())->not->toBeNull();

    $other = ContractTemplate::factory()->for($account, 'account')->create();

    $this->actingAs($user)->delete(route('contract-templates.destroy', $default));

    expect($default->fresh())->toBeNull()
        ->and($other->fresh()->is_default)->toBeTrue();
});

test('a page break is kept when the template is saved', function () {
    ['account' => $account, 'user' => $user] = contractTemplateScenario();

    $this->actingAs($user)
        ->post(route('contract-templates.store'), [
            'name' => 'Com quebra',
            'body' => '<p>Página 1</p><hr data-page-break="true"><p>Página 2</p>',
        ])
        ->assertSessionHasNoErrors();

    expect($account->contractTemplates()->sole()->body)->toContain('<hr data-page-break="true" />');
});

test('an unsaved template can be previewed as PDF, with variable labels when there is no lease', function () {
    ['user' => $user] = contractTemplateScenario();

    $this->actingAs($user)
        ->post(route('contract-templates.preview'), ['body' => '<p>Locatário: <span data-variable="tenant.name"></span></p>'])
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    $this->actingAs($user)
        ->post(route('contract-templates.preview'), ['body' => '<p><span data-variable="tenant.salary"></span></p>'])
        ->assertSessionHasErrors('body');
});

test('a user cannot manage templates from another account', function () {
    ['user' => $user] = contractTemplateScenario();
    $otherTemplate = ContractTemplate::factory()->create();

    $this->actingAs($user)->get(route('contract-templates.edit', $otherTemplate))->assertNotFound();
    $this->actingAs($user)->put(route('contract-templates.update', $otherTemplate), ['name' => 'X', 'body' => '<p>X</p>'])->assertNotFound();
    $this->actingAs($user)->patch(route('contract-templates.default', $otherTemplate))->assertNotFound();
    $this->actingAs($user)->delete(route('contract-templates.destroy', $otherTemplate))->assertNotFound();
});
