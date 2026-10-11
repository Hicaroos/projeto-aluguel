<?php

use App\Actions\ContractTemplates\RenderExpenseDocument;
use App\Actions\ContractTemplates\ResolveContractVariables;
use App\Actions\Properties\SyncPropertyOwners;
use App\Enums\ContractTemplateKind;
use App\Enums\ExpenseType;
use App\Enums\Role;
use App\Models\Account;
use App\Models\Branch;
use App\Models\ContractTemplate;
use App\Models\Expense;
use App\Models\ExpenseDocument;
use App\Models\Owner;
use App\Models\Property;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo('2026-10-15 09:00:00');
});

/**
 * An account with a roof repair paid on a house, done by a named provider.
 *
 * @return array{account: Account, user: User, owner: Owner, property: Property, expense: Expense}
 */
function serviceExpenseScenario(bool $agency = false): array
{
    $account = $agency
        ? Account::factory()->agency()->create(['name' => 'Imobiliária Silva', 'legal_name' => 'Silva Imóveis Ltda', 'document' => '12345678000195'])
        : Account::factory()->create();
    $owner = Owner::factory()->for($account, 'account')->create(['name' => 'Maria Proprietária', 'cpf_cnpj' => '11122233344']);
    $property = Property::factory()->for($account, 'account')->for($owner, 'owner')->create([
        'street' => 'Rua das Flores',
        'number' => '10',
        'neighborhood' => 'Centro',
        'city' => 'Ouricuri',
        'state' => 'PE',
        'zip_code' => '56200000',
        'complement' => null,
    ]);

    return [
        'account' => $account,
        'user' => User::factory()->for($account, 'account')->create(),
        'owner' => $owner,
        'property' => $property,
        'expense' => Expense::factory()->for($account, 'account')->for($property)->create([
            'type' => ExpenseType::Maintenance,
            'description' => 'Conserto do telhado',
            'provider_name' => 'José Telhados',
            'provider_document' => '55566677788',
            'amount' => 1250.50,
            'due_date' => '2026-10-10',
            'payment_date' => '2026-10-12',
            'status' => 'paid',
        ]),
    ];
}

function serviceReceiptTemplate(Account $account, ?string $body = null): ContractTemplate
{
    return ContractTemplate::factory()->for($account, 'account')->create([
        'kind' => ContractTemplateKind::ServiceReceipt,
        'body' => $body ?? File::get(ContractTemplateKind::ServiceReceipt->defaultTemplatePath()),
    ]);
}

test('the service receipt is filled with the provider, the job and the agency paying for it', function () {
    ['account' => $account, 'expense' => $expense] = serviceExpenseScenario(agency: true);

    $html = app(RenderExpenseDocument::class)->handle(serviceReceiptTemplate($account), $expense);

    expect($html)
        ->toContain('Eu, José Telhados, inscrito(a) no CPF/CNPJ sob o nº 555.666.777-88')
        ->toContain('recebi de Silva Imóveis Ltda, inscrita no CNPJ sob o nº 12.345.678/0001-95')
        ->toContain('R$ 1.250,50 (mil duzentos e cinquenta reais e cinquenta centavos)')
        ->toContain('Conserto do telhado')
        ->toContain('situado em Rua das Flores, 10, Centro, Ouricuri/PE, CEP 56200-000, de propriedade de Maria Proprietária')
        ->toContain('Ouricuri/PE, 12 de outubro de 2026.')
        ->toContain('PRESTADOR: José Telhados')
        ->toContain('CPF/CNPJ: 555.666.777-88');
});

test('owners managing their own properties pay the provider themselves, co-owners included', function () {
    ['account' => $account, 'property' => $property, 'expense' => $expense] = serviceExpenseScenario();
    $coOwner = Owner::factory()->for($account, 'account')->create(['name' => 'João Coproprietário', 'cpf_cnpj' => null]);
    app(SyncPropertyOwners::class)->handle($property, [$coOwner->id => 40]);

    $html = app(RenderExpenseDocument::class)->handle(
        serviceReceiptTemplate($account, '<p><span data-variable="service.contractor"></span> | <span data-variable="service.property_owner"></span></p>'),
        $expense->refresh(),
    );

    expect($html)->toContain('Maria Proprietária, inscrito(a) no CPF sob o nº 111.222.333-44 e João Coproprietário | Maria Proprietária e João Coproprietário');
});

test('a missing provider and payment date become blank lines to fill by hand', function () {
    ['account' => $account, 'expense' => $expense] = serviceExpenseScenario();
    $expense->update(['provider_name' => null, 'provider_document' => null, 'payment_date' => null, 'status' => 'pending', 'description' => null]);

    $html = app(RenderExpenseDocument::class)->handle(
        serviceReceiptTemplate($account, '<p><span data-variable="provider.name"></span> · <span data-variable="service.payment_date"></span> · <span data-variable="service.description"></span></p>'),
        $expense->refresh(),
    );

    expect($html)->toContain(ResolveContractVariables::BLANK.' · '.ResolveContractVariables::BLANK.' · Manutenção');
});

test('the service receipt is generated as PDF from the default or the chosen template', function () {
    ['account' => $account, 'user' => $user, 'expense' => $expense] = serviceExpenseScenario();
    $custom = serviceReceiptTemplate($account, '<p>Recibo de <span data-variable="provider.name"></span></p>');

    $this->actingAs($user)
        ->get(route('expenses.service-receipt', $expense))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    $this->actingAs($user)
        ->get(route('expenses.service-receipt', ['expense' => $expense->id, 'template' => $custom->id]))
        ->assertOk();

    $leaseTemplate = ContractTemplate::factory()->for($account, 'account')->create();

    $this->actingAs($user)
        ->get(route('expenses.service-receipt', ['expense' => $expense->id, 'template' => $leaseTemplate->id]))
        ->assertNotFound();
});

test('the account gets the standard service receipt template on the first receipt', function () {
    ['account' => $account, 'user' => $user, 'expense' => $expense] = serviceExpenseScenario();

    $this->actingAs($user)->get(route('expenses.service-receipt', $expense))->assertOk();

    $template = $account->contractTemplates()->where('kind', ContractTemplateKind::ServiceReceipt)->sole();

    expect($template->name)->toBe('Recibo de serviço padrão')
        ->and($template->is_default)->toBeTrue()
        ->and($template->body)->toContain('data-variable="general.service_signature"');
});

test('only service expenses have a receipt', function () {
    ['user' => $user, 'expense' => $expense] = serviceExpenseScenario();
    $expense->update(['type' => ExpenseType::CondoFee]);

    $this->actingAs($user)
        ->get(route('expenses.service-receipt', $expense))
        ->assertNotFound();
});

test('service receipts and documents of other accounts are out of reach', function () {
    ['expense' => $expense] = serviceExpenseScenario();
    $document = ExpenseDocument::factory()->for($expense)->create();
    $stranger = User::factory()->for(Account::factory(), 'account')->create();

    $this->actingAs($stranger)->get(route('expenses.service-receipt', $expense))->assertNotFound();
    $this->actingAs($stranger)->get(route('expense-documents.show', $document))->assertNotFound();
    $this->actingAs($stranger)->delete(route('expense-documents.destroy', $document))->assertNotFound();
    $this->actingAs($stranger)
        ->post(route('expenses.documents.store', $expense), [
            'type' => 'signed_receipt',
            'file' => UploadedFile::fake()->create('recibo.pdf', 100, 'application/pdf'),
        ])
        ->assertNotFound();
});

test('the signed receipt is attached to the expense and removed with it', function () {
    Storage::fake(ExpenseDocument::DISK);
    ['user' => $user, 'expense' => $expense] = serviceExpenseScenario();

    $this->actingAs($user)
        ->from(route('expenses.index', ['month' => '2026-10']))
        ->post(route('expenses.documents.store', $expense), [
            'type' => 'signed_receipt',
            'file' => UploadedFile::fake()->create('recibo-assinado.pdf', 300, 'application/pdf'),
        ])
        ->assertRedirect(route('expenses.index', ['month' => '2026-10', 'show' => $expense->id]));

    $document = ExpenseDocument::where('expense_id', $expense->id)->sole();

    Storage::disk(ExpenseDocument::DISK)->assertExists($document->path);

    $this->actingAs($user)->get(route('expense-documents.show', $document))->assertOk();
    $this->actingAs($user)->get(route('expense-documents.download', $document))->assertDownload('recibo-assinado.pdf');

    $this->actingAs($user)
        ->get(route('expenses.index', ['month' => '2026-10', 'show' => $expense->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('expenses.data.0.documents', 1)
            ->where('selected.documents.0.name', 'recibo-assinado.pdf')
            ->where('selected.documents.0.type', 'signed_receipt')
        );

    $this->actingAs($user)->delete(route('expenses.destroy', $expense));

    $this->assertModelMissing($document);
    Storage::disk(ExpenseDocument::DISK)->assertMissing($document->path);
});

test('an attached document can be removed on its own', function () {
    Storage::fake(ExpenseDocument::DISK);
    ['user' => $user, 'expense' => $expense] = serviceExpenseScenario();

    $this->actingAs($user)->post(route('expenses.documents.store', $expense), [
        'type' => 'invoice',
        'file' => UploadedFile::fake()->create('nota.jpg', 200, 'image/jpeg'),
    ]);

    $document = ExpenseDocument::where('expense_id', $expense->id)->sole();

    $this->actingAs($user)
        ->delete(route('expense-documents.destroy', $document))
        ->assertRedirect();

    $this->assertModelMissing($document);
    Storage::disk(ExpenseDocument::DISK)->assertMissing($document->path);
    expect($expense->fresh())->not->toBeNull();
});

test('only pdfs and images are attached to expenses', function () {
    Storage::fake(ExpenseDocument::DISK);
    ['user' => $user, 'expense' => $expense] = serviceExpenseScenario();

    $this->actingAs($user)
        ->post(route('expenses.documents.store', $expense), [
            'type' => 'other',
            'file' => UploadedFile::fake()->create('planilha.xlsx', 100, 'application/vnd.ms-excel'),
        ])
        ->assertSessionHasErrors(['file' => 'Envie um PDF ou uma imagem (JPG, PNG ou WebP).']);
});

test('agents without access to finance neither attach documents nor generate receipts', function () {
    Storage::fake(ExpenseDocument::DISK);
    ['account' => $account, 'property' => $property, 'expense' => $expense] = serviceExpenseScenario(agency: true);
    $branch = Branch::factory()->for($account, 'account')->create();
    $property->update(['branch_id' => $branch->id]);
    $agent = User::factory()->for($account, 'account')->withRole(Role::Agent)->create();
    $agent->branches()->attach($branch);

    $this->actingAs($agent)->get(route('expenses.service-receipt', $expense))->assertForbidden();
    $this->actingAs($agent)
        ->post(route('expenses.documents.store', $expense), [
            'type' => 'signed_receipt',
            'file' => UploadedFile::fake()->create('recibo.pdf', 100, 'application/pdf'),
        ])
        ->assertForbidden();
});

test('services keep their provider and other expenses drop it', function () {
    ['user' => $user, 'property' => $property, 'expense' => $expense] = serviceExpenseScenario();

    $payload = [
        'property_id' => $property->id,
        'type' => 'maintenance',
        'description' => 'Troca do chuveiro',
        'provider_name' => 'Pedro Eletricista',
        'provider_document' => '123.456.789-00',
        'amount' => '180',
        'due_date' => '2026-10-20',
    ];

    $this->actingAs($user)->put(route('expenses.update', $expense), $payload)->assertSessionHasNoErrors();

    expect($expense->refresh())
        ->provider_name->toBe('Pedro Eletricista')
        ->provider_document->toBe('12345678900');

    $this->actingAs($user)->put(route('expenses.update', $expense), [...$payload, 'type' => 'condo_fee'])->assertSessionHasNoErrors();

    expect($expense->refresh())
        ->provider_name->toBeNull()
        ->provider_document->toBeNull();

    $this->actingAs($user)
        ->put(route('expenses.update', $expense), [...$payload, 'provider_document' => '123'])
        ->assertSessionHasErrors(['provider_document' => 'Informe um CPF (11 dígitos) ou CNPJ (14 dígitos) válido.']);
});

test('the service receipt template can be previewed with the latest service expense', function () {
    ['user' => $user] = serviceExpenseScenario();

    $this->actingAs($user)
        ->post(route('contract-templates.preview', ['kind' => 'service_receipt']), [
            'body' => '<p>Prestador: <span data-variable="provider.name"></span></p>',
        ])
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    $this->actingAs($user)
        ->post(route('contract-templates.preview', ['kind' => 'service_receipt']), [
            'body' => '<p><span data-variable="tenant.name"></span></p>',
        ])
        ->assertSessionHasErrors('body');
});
