<?php

use App\Actions\ContractTemplates\RenderLeaseContract;
use App\Actions\ContractTemplates\ResolveContractVariables;
use App\Enums\GuaranteeType;
use App\Enums\MaritalStatus;
use App\Models\Account;
use App\Models\ContractTemplate;
use App\Models\Guarantor;
use App\Models\Lease;
use App\Models\Owner;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\User;

beforeEach(function () {
    $this->travelTo('2026-10-15 09:00:00');
});

/**
 * @return array{account: Account, user: User, lease: Lease}
 */
function leaseContractScenario(array $leaseAttributes = []): array
{
    $account = Account::factory()->create();
    $owner = Owner::factory()->for($account, 'account')->create(['name' => 'Carlos Dono', 'cpf_cnpj' => '11122233344']);
    $property = Property::factory()->for($account, 'account')->for($owner, 'owner')->create([
        'street' => 'Rua das Flores', 'number' => '100', 'complement' => null, 'neighborhood' => 'Centro',
        'city' => 'Curitiba', 'state' => 'PR', 'zip_code' => '80000000',
    ]);
    $tenant = Tenant::factory()->for($account, 'account')->create([
        'name' => 'Maria Souza',
        'cpf_cnpj' => '12345678900',
        'nationality' => 'brasileira',
        'marital_status' => MaritalStatus::Married,
        'profession' => null,
        'rg' => null,
    ]);

    return [
        'account' => $account,
        'user' => User::factory()->for($account, 'account')->create(),
        'lease' => Lease::factory()->for($account, 'account')->for($property)->for($tenant)->create([
            'amount' => '1500.20',
            'start_date' => '2026-01-01',
            'end_date' => '2028-06-30',
            ...$leaseAttributes,
        ]),
    ];
}

function renderContract(Lease $lease, string $body): string
{
    $template = ContractTemplate::factory()->for($lease->account, 'account')->create(['body' => $body]);

    return app(RenderLeaseContract::class)->handle($template, $lease->fresh());
}

test('rendering fills the variables with the formatted lease details', function () {
    ['lease' => $lease] = leaseContractScenario();

    $html = renderContract($lease, '<p><span data-variable="tenant.qualification"></span>; <span data-variable="lease.amount"></span> (<span data-variable="lease.amount_in_words"></span>); <span data-variable="lease.start_date"></span>; <span data-variable="lease.duration_months"></span> meses; <span data-variable="general.court"></span>.</p>');

    expect($html)
        ->toContain('Maria Souza, brasileira, casado(a), inscrito(a) no CPF sob o nº 123.456.789-00')
        ->toContain('R$ 1.500,20 (mil e quinhentos reais e vinte centavos)')
        ->toContain('1 de janeiro de 2026')
        ->toContain('30 meses')
        ->toContain('Curitiba/PR')
        ->not->toContain('data-variable');
});

test('rendering leaves a blank line for missing details and escapes the values', function () {
    ['lease' => $lease] = leaseContractScenario(['notes' => '<b>Pagamento</b> & multa']);

    $html = renderContract($lease, '<p>Profissão: <span data-variable="tenant.profession"></span>. <span data-variable="lease.notes"></span></p>');

    expect($html)
        ->toContain('Profissão: '.ResolveContractVariables::BLANK)
        ->toContain('&lt;b&gt;Pagamento&lt;/b&gt; &amp; multa')
        ->not->toContain('<b>');
});

test('the guarantee clause follows the guarantee type', function (GuaranteeType $type, array $attributes, string $expected) {
    ['lease' => $lease] = leaseContractScenario(['guarantee_type' => $type, ...$attributes]);

    if ($type === GuaranteeType::Guarantor) {
        Guarantor::factory()->for($lease)->create(['name' => 'José Fiador', 'spouse_name' => 'Ana Fiadora']);
    }

    expect(renderContract($lease, '<p><span data-variable="guarantee.clause"></span></p>'))->toContain($expected);
})->with([
    'sem garantia' => [GuaranteeType::None, [], 'sem garantia locatícia'],
    'caução' => [GuaranteeType::Deposit, ['deposit_amount' => '3000.00'], 'R$ 3.000,00 (três mil reais)'],
    'fiador' => [GuaranteeType::Guarantor, [], 'FIADOR e principal pagador, solidariamente responsável com o LOCATÁRIO por todas as obrigações aqui assumidas até a efetiva entrega das chaves, José Fiador'],
    'seguro-fiança' => [GuaranteeType::SuretyBond, ['surety_insurer' => 'Seguradora X', 'surety_policy_number' => 'AP-1'], 'seguradora Seguradora X, apólice nº AP-1'],
]);

test('the signatures include the guarantor and spouse only when there is a guarantor', function () {
    ['lease' => $lease] = leaseContractScenario();

    expect(renderContract($lease, '<p><span data-variable="general.signatures"></span></p>'))
        ->toContain('LOCATÁRIO: Maria Souza')
        ->toContain('<br>')
        ->not->toContain('FIADOR');

    $lease->update(['guarantee_type' => GuaranteeType::Guarantor]);
    Guarantor::factory()->for($lease)->create(['name' => 'José Fiador', 'spouse_name' => 'Ana Fiadora']);

    expect(renderContract($lease, '<p><span data-variable="general.signatures"></span></p>'))
        ->toContain('FIADOR: José Fiador')
        ->toContain('CÔNJUGE DO FIADOR: Ana Fiadora');
});

test('the contract is generated as a PDF using the default template when none is chosen', function () {
    ['account' => $account, 'user' => $user, 'lease' => $lease] = leaseContractScenario();

    $response = $this->actingAs($user)->get(route('leases.contract', $lease));

    $response->assertOk()->assertHeader('content-type', 'application/pdf');

    expect($account->contractTemplates()->sole()->is_default)->toBeTrue();

    $template = ContractTemplate::factory()->for($account, 'account')->create();

    $this->actingAs($user)
        ->get(route('leases.contract', ['lease' => $lease, 'template' => $template->id]))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

test('a contract cannot be generated for another account lease or template', function () {
    ['user' => $user, 'lease' => $lease] = leaseContractScenario();
    $otherLease = Lease::factory()->create();
    $otherTemplate = ContractTemplate::factory()->create();

    $this->actingAs($user)->get(route('leases.contract', $otherLease))->assertNotFound();
    $this->actingAs($user)
        ->get(route('leases.contract', ['lease' => $lease, 'template' => $otherTemplate->id]))
        ->assertNotFound();
});
