<?php

use App\Actions\ContractTemplates\RenderSaleDocument;
use App\Actions\ContractTemplates\ResolveContractVariables;
use App\Enums\ContractTemplateKind;
use App\Enums\MaritalStatus;
use App\Enums\Role;
use App\Models\Account;
use App\Models\Branch;
use App\Models\ContractTemplate;
use App\Models\Owner;
use App\Models\Property;
use App\Models\Sale;
use App\Models\SaleDocument;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->travelTo('2026-10-15 09:00:00');
});

/**
 * An account with a sale of a house, with the buyer fully qualified.
 *
 * @return array{account: Account, user: User, owner: Owner, property: Property, sale: Sale}
 */
function saleDocumentScenario(bool $agency = false): array
{
    $account = $agency
        ? Account::factory()->agency()->create(['name' => 'Imobiliária Silva', 'document' => '12345678000195', 'creci' => '1234-J'])
        : Account::factory()->create();
    $owner = Owner::factory()->for($account, 'account')->create(['name' => 'Maria Vendedora', 'cpf_cnpj' => '11122233344']);
    $property = Property::factory()->for($account, 'account')->for($owner, 'owner')->create([
        'street' => 'Rua das Flores',
        'number' => '10',
        'city' => 'Ouricuri',
        'state' => 'PE',
        'bedrooms' => 3,
        'suites' => 1,
        'land_area' => 250,
        'land_front' => 10,
        'land_depth' => 25,
        'registration_number' => '12.345',
    ]);

    return [
        'account' => $account,
        'user' => User::factory()->for($account, 'account')->create(),
        'owner' => $owner,
        'property' => $property,
        'sale' => Sale::factory()->for($account, 'account')->create([
            'property_id' => $property->id,
            'seller_owner_id' => $owner->id,
            'buyer_name' => 'Carlos Comprador',
            'buyer_document' => '55566677788',
            'buyer_nationality' => 'brasileiro',
            'buyer_marital_status' => MaritalStatus::Married,
            'buyer_profession' => 'comerciante',
            'price' => 300000,
            'down_payment' => 30000,
            'sold_on' => '2026-10-14',
            'deed_deadline' => '2026-12-14',
            'payment_plan' => 'financing',
            'commission_percent' => $agency ? 6 : null,
            'commission_amount' => $agency ? 18000 : null,
        ]),
    ];
}

test('the sale contract is filled with the sale details', function () {
    ['sale' => $sale] = saleDocumentScenario(agency: true);
    $template = ContractTemplate::factory()->for($sale->account, 'account')->create([
        'kind' => ContractTemplateKind::SaleContract,
        'body' => File::get(ContractTemplateKind::SaleContract->defaultTemplatePath()),
    ]);

    $html = app(RenderSaleDocument::class)->handle($template, $sale);

    expect($html)
        ->toContain('Maria Vendedora')
        ->toContain('Carlos Comprador, brasileiro, casado(a), comerciante')
        ->toContain('R$ 300.000,00')
        ->toContain('R$ 30.000,00')
        ->toContain('R$ 270.000,00')
        ->toContain('12.345')
        ->toContain('3 quartos, sendo 1 suíte')
        ->toContain('terreno de 250 m² (10 x 25 m)')
        ->toContain('14 de dezembro de 2026')
        ->toContain('Financiamento')
        ->toContain('intermediada por Imobiliária Silva, inscrita no CNPJ sob o nº 12.345.678/0001-95, CRECI 1234-J')
        ->toContain('R$ 18.000,00')
        ->toContain('COMPRADOR: Carlos Comprador');
});

test('missing sale details become a blank line and single owners sell without intermediation', function () {
    ['sale' => $sale] = saleDocumentScenario();
    $sale->update(['down_payment' => null, 'deed_deadline' => null]);
    $template = ContractTemplate::factory()->for($sale->account, 'account')->create([
        'kind' => ContractTemplateKind::DownPaymentReceipt,
        'body' => '<p><span data-variable="sale.down_payment"></span> · <span data-variable="sale.deed_deadline"></span> · <span data-variable="sale.intermediation_clause"></span></p>',
    ]);

    $html = app(RenderSaleDocument::class)->handle($template, $sale->refresh());

    expect($html)
        ->toContain(ResolveContractVariables::BLANK.' · '.ResolveContractVariables::BLANK)
        ->toContain('sem intermediação imobiliária');
});

test('sale documents are generated as PDF from the default or the chosen template', function () {
    ['user' => $user, 'sale' => $sale, 'account' => $account] = saleDocumentScenario();
    $custom = ContractTemplate::factory()->for($account, 'account')->create([
        'kind' => ContractTemplateKind::DownPaymentReceipt,
        'body' => '<p>Recibo de <span data-variable="buyer.name"></span></p>',
    ]);

    $this->actingAs($user)
        ->get(route('sales.generate', ['sale' => $sale->id, 'kind' => 'sale_contract']))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    expect(ContractTemplate::where('account_id', $account->id)->where('kind', 'sale_contract')->where('is_default', true)->exists())->toBeTrue();

    $this->actingAs($user)
        ->get(route('sales.generate', ['sale' => $sale->id, 'kind' => 'down_payment_receipt', 'template' => $custom->id]))
        ->assertOk();

    $leaseTemplate = ContractTemplate::factory()->for($account, 'account')->create();

    $this->actingAs($user)
        ->get(route('sales.generate', ['sale' => $sale->id, 'kind' => 'down_payment_receipt', 'template' => $leaseTemplate->id]))
        ->assertNotFound();

    $this->actingAs($user)
        ->get(route('sales.generate', ['sale' => $sale->id, 'kind' => 'lease']))
        ->assertNotFound();
});

test('sale documents of other accounts are out of reach', function () {
    ['sale' => $sale] = saleDocumentScenario();
    $stranger = User::factory()->for(Account::factory(), 'account')->create();

    $this->actingAs($stranger)
        ->get(route('sales.generate', ['sale' => $sale->id, 'kind' => 'sale_contract']))
        ->assertNotFound();
});

test('signed documents are attached to the sale and removed with it', function () {
    Storage::fake(SaleDocument::DISK);
    ['user' => $user, 'sale' => $sale] = saleDocumentScenario();

    $this->actingAs($user)
        ->post(route('sales.documents.store', $sale), [
            'type' => 'signed_contract',
            'file' => UploadedFile::fake()->create('contrato.pdf', 300, 'application/pdf'),
        ])
        ->assertRedirect(route('sales.index', ['month' => '2026-10', 'show' => $sale->id]));

    $document = SaleDocument::where('sale_id', $sale->id)->sole();

    Storage::disk(SaleDocument::DISK)->assertExists($document->path);

    $this->actingAs($user)->get(route('sale-documents.show', $document))->assertOk();
    $this->actingAs($user)->get(route('sale-documents.download', $document))->assertDownload('contrato.pdf');

    $this->actingAs($user)->delete(route('sales.destroy', $sale));

    $this->assertModelMissing($document);
    Storage::disk(SaleDocument::DISK)->assertMissing($document->path);
});

test('only pdfs and images are attached to sales', function () {
    Storage::fake(SaleDocument::DISK);
    ['user' => $user, 'sale' => $sale] = saleDocumentScenario();

    $this->actingAs($user)
        ->post(route('sales.documents.store', $sale), [
            'type' => 'deed',
            'file' => UploadedFile::fake()->create('planilha.xlsx', 100, 'application/vnd.ms-excel'),
        ])
        ->assertSessionHasErrors(['file' => 'Envie um PDF ou uma imagem (JPG, PNG ou WebP).']);
});

test('sales can be edited to complete the details for the contract', function () {
    ['user' => $user, 'sale' => $sale] = saleDocumentScenario(agency: true);

    $this->actingAs($user)
        ->put(route('sales.update', $sale), [
            'buyer_name' => 'Carlos Comprador',
            'buyer_document' => '555.666.777-88',
            'price' => '310000',
            'down_payment' => '31000',
            'sold_on' => '2026-10-14',
            'deed_deadline' => '2026-12-20',
            'commission_percent' => '5',
            'buyer' => [
                'rg' => '1234567',
                'marital_status' => 'single',
                'street' => 'Rua Nova',
                'number' => '5',
                'city' => 'Araripina',
                'state' => 'PE',
            ],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('sales.index', ['month' => '2026-10', 'show' => $sale->id]));

    $sale->refresh();

    expect($sale->price)->toBe('310000.00')
        ->and($sale->commission_amount)->toBe('15500.00')
        ->and($sale->buyer_rg)->toBe('1234567')
        ->and($sale->buyer_marital_status)->toBe(MaritalStatus::Single)
        ->and($sale->buyer_city)->toBe('Araripina')
        ->and($sale->deed_deadline->toDateString())->toBe('2026-12-20');
});

test('the down payment cannot exceed the price nor the deed come before the sale', function () {
    ['user' => $user, 'sale' => $sale] = saleDocumentScenario();

    $this->actingAs($user)
        ->put(route('sales.update', $sale), [
            'buyer_name' => 'Carlos Comprador',
            'price' => '100000',
            'down_payment' => '150000',
            'sold_on' => '2026-10-14',
            'deed_deadline' => '2026-10-01',
        ])
        ->assertSessionHasErrors([
            'down_payment' => 'O sinal não pode passar do valor da venda.',
            'deed_deadline' => 'O prazo da escritura não pode ser antes da data da venda.',
        ]);
});

test('finance members generate documents but do not change sales', function () {
    ['account' => $account, 'sale' => $sale] = saleDocumentScenario(agency: true);
    $branch = Branch::factory()->for($account, 'account')->create();
    $sale->property->update(['branch_id' => $branch->id]);
    $finance = User::factory()->for($account, 'account')->withRole(Role::Finance)->create();
    $finance->branches()->attach($branch);

    $this->actingAs($finance)
        ->get(route('sales.generate', ['sale' => $sale->id, 'kind' => 'sale_contract']))
        ->assertOk();

    $this->actingAs($finance)
        ->put(route('sales.update', $sale), ['buyer_name' => 'X', 'price' => '1', 'sold_on' => '2026-10-14'])
        ->assertForbidden();
});
