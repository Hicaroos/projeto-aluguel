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

test('index creates the standard template once for the account', function () {
    ['account' => $account, 'user' => $user] = contractTemplateScenario();
    ContractTemplate::factory()->create();

    $this->actingAs($user)
        ->get(route('contract-templates.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('contract-templates/Index')
            ->has('templates', 1)
            ->where('templates.0.name', 'Contrato residencial padrão')
            ->where('templates.0.is_default', true)
        );

    $this->actingAs($user)->get(route('contract-templates.index'));

    $template = $account->contractTemplates()->sole();

    expect($template->body)->toContain('data-variable="tenant.qualification"');
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

test('a user cannot manage templates from another account', function () {
    ['user' => $user] = contractTemplateScenario();
    $otherTemplate = ContractTemplate::factory()->create();

    $this->actingAs($user)->get(route('contract-templates.edit', $otherTemplate))->assertNotFound();
    $this->actingAs($user)->put(route('contract-templates.update', $otherTemplate), ['name' => 'X', 'body' => '<p>X</p>'])->assertNotFound();
    $this->actingAs($user)->patch(route('contract-templates.default', $otherTemplate))->assertNotFound();
    $this->actingAs($user)->delete(route('contract-templates.destroy', $otherTemplate))->assertNotFound();
});
