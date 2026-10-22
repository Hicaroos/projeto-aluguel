<?php

namespace Database\Seeders;

use App\Actions\ContractTemplates\BuildDocumentPdf;
use App\Actions\ContractTemplates\EnsureDefaultContractTemplate;
use App\Actions\ContractTemplates\RenderExpenseDocument;
use App\Actions\ContractTemplates\RenderLeaseContract;
use App\Actions\ContractTemplates\RenderSaleDocument;
use App\Actions\Deals\EnsureDefaultDealStages;
use App\Actions\Deals\MoveDeal;
use App\Actions\Deals\RegisterSale;
use App\Actions\Leases\ApplyLeaseAdjustment;
use App\Actions\Leases\CreateLease;
use App\Actions\Leases\FinishLease;
use App\Actions\Leases\RenderLeaseRenewalAmendment;
use App\Actions\Leases\RenewLease;
use App\Actions\Leases\SettleLeaseDeposit;
use App\Actions\Leases\SyncLeasePayments;
use App\Actions\Messages\ComposeWhatsAppMessage;
use App\Actions\Payments\CalculateLateCharges;
use App\Actions\Payments\CreateExtraCharge;
use App\Actions\Payments\RegisterReceipt;
use App\Actions\Payouts\RegisterOwnerPayout;
use App\Actions\Payouts\SummarizeOwnerBalances;
use App\Actions\Properties\SyncPropertyOwners;
use App\Actions\Showcase\RegisterShowcaseListing;
use App\Actions\Showcase\RegisterShowcaseVisit;
use App\Enums\AccountType;
use App\Enums\AdjustmentIndex;
use App\Enums\ContractTemplateKind;
use App\Enums\DealType;
use App\Enums\ExpenseDocumentType;
use App\Enums\ExpensePayer;
use App\Enums\ExpenseStatus;
use App\Enums\ExpenseType;
use App\Enums\GuaranteeType;
use App\Enums\LeadInterest;
use App\Enums\LeadSource;
use App\Enums\LeaseDocumentType;
use App\Enums\LeasePurpose;
use App\Enums\LeaseStatus;
use App\Enums\MaritalStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentPlan;
use App\Enums\PaymentStatus;
use App\Enums\PayoutMode;
use App\Enums\PayoutSchedule;
use App\Enums\PropertyAmenity;
use App\Enums\PropertyStatus;
use App\Enums\PropertyType;
use App\Enums\ProposalStatus;
use App\Enums\Role;
use App\Enums\SaleDocumentType;
use App\Enums\ShowcaseColor;
use App\Enums\VisitStatus;
use App\Models\Account;
use App\Models\Branch;
use App\Models\ContractTemplate;
use App\Models\Deal;
use App\Models\DealStage;
use App\Models\Expense;
use App\Models\ExpenseDocument;
use App\Models\Lead;
use App\Models\Lease;
use App\Models\LeaseAdjustment;
use App\Models\LeaseDocument;
use App\Models\Owner;
use App\Models\OwnerPayout;
use App\Models\Payment;
use App\Models\Property;
use App\Models\PropertyPhoto;
use App\Models\Sale;
use App\Models\SaleDocument;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Http\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Demo data to show the whole system to a client: a single owner and an agency with two branches, its
 * team, owners with different payout terms, co-owned properties, leases with months of history (late,
 * partial and overdue rent, a renewal with a rent adjustment, a finished lease with its
 * deposit settled), extra charges, expenses with documents, payouts with their proofs, WhatsApp messages,
 * the sales funnel with visits and proposals, two sales, and the public showcase with photos and requests.
 *
 * Every date is counted back from today, so the dashboard and the agenda always look current. Everything
 * goes through the app's own actions with the clock moved to when it happened. Every user signs in with
 * the password "password".
 */
class DemoSeeder extends Seeder
{
    private CarbonImmutable $today;

    private Account $agency;

    private Branch $matriz;

    private Branch $araripina;

    /** @var array<string, User> */
    private array $team = [];

    /** @var array<string, Owner> */
    private array $owners = [];

    /**
     * Seed the demo data.
     */
    public function run(): void
    {
        $this->today = CarbonImmutable::today();

        $this->singleOwner();
        $this->agency();

        $this->travelBack();
    }

    /**
     * A single owner managing their own two houses.
     */
    private function singleOwner(): void
    {
        $account = $this->at($this->day(-7, 1), fn (): Account => Account::create([
            'name' => 'Hicaro Costa',
            'type' => AccountType::SingleOwner,
            'phone' => '87999990001',
            'city' => 'Ouricuri',
            'state' => 'PE',
        ]));

        $owner = $account->owners()->create([
            'name' => 'Hicaro Costa',
            'cpf_cnpj' => '12345678909',
            'email' => 'proprietario@teste.com',
            'phone' => '87999990001',
            'pix_key' => 'proprietario@teste.com',
        ]);

        $account->users()->create(['name' => 'Hicaro Costa', 'email' => 'proprietario@teste.com', 'password' => 'password'])
            ->forceFill(['email_verified_at' => now()])
            ->save();

        $house = $this->property($account, null, $owner, ['street' => 'Rua José Bezerra', 'number' => '210', 'rent_amount' => 950, 'bedrooms' => 2, 'bathrooms' => 1, 'parking_spaces' => 1, 'built_area' => 70]);
        $this->property($account, null, $owner, ['street' => 'Rua do Comércio', 'number' => '45', 'rent_amount' => 1200, 'for_sale' => true, 'sale_price' => 190000, 'bedrooms' => 3, 'bathrooms' => 2, 'parking_spaces' => 1, 'built_area' => 95]);

        $lease = $this->lease($account, $house, $this->tenant($account, 'Joana Martins', '98765432100', ['profession' => 'Professora', 'marital_status' => MaritalStatus::Married]), -6, 950, 10);
        $this->receiveRent($lease, late: [-3 => 6]);

        $this->expense($account, $house, ExpenseType::PropertyTax, 'IPTU – parcela do mês', 62, $this->day(-1, 15), $this->day(-1, 14));
    }

    /**
     * The agency: its team and settings, its owners and properties, months of rent and payouts, the sales
     * funnel and the showcase.
     */
    private function agency(): void
    {
        $this->agency = $this->at($this->day(-15, 1), fn (): Account => Account::create([
            'name' => 'Imobizilla',
            'legal_name' => 'Imobizilla Negócios Imobiliários Ltda',
            'type' => AccountType::Agency,
            'document' => '12345678000195',
            'creci' => '1234-J',
            'phone' => '8738741234',
            'email' => 'contato@imobizilla.test',
            'pix_key' => '12345678000195',
            'zip_code' => '56200000',
            'street' => 'Rua Siqueira Campos',
            'number' => '10',
            'neighborhood' => 'Centro',
            'city' => 'Ouricuri',
            'state' => 'PE',
            'sale_commission_percent' => 6,
            'admin_fee_percent' => 10,
            'intermediation_fee_percent' => 100,
            'renewal_fee_percent' => 50,
            'admin_fee_with_intermediation' => false,
            'admin_fee_on_extra_charges' => false,
            'extra_charges_to_owner' => true,
            'late_charges_owner_percent' => 100,
            'payout_mode' => PayoutMode::Received,
            'payout_schedule' => PayoutSchedule::DaysAfter,
            'payout_days' => 5,
            'payout_day' => 10,
            'showcase_enabled' => true,
            'showcase_slug' => 'imobizilla',
            'showcase_whatsapp' => '87999990000',
            'showcase_color' => ShowcaseColor::Forest,
            'showcase_instagram' => 'https://instagram.com/imobizilla',
            'showcase_facebook' => 'https://facebook.com/imobizilla',
        ]));

        $this->matriz = $this->agency->branches()->create(['name' => Branch::MAIN_BRANCH_NAME, 'phone' => '8738741234', 'zip_code' => '56200000', 'street' => 'Rua Siqueira Campos', 'number' => '10', 'neighborhood' => 'Centro', 'city' => 'Ouricuri', 'state' => 'PE']);
        $this->araripina = $this->agency->branches()->create(['name' => 'Araripina', 'phone' => '8738731234', 'zip_code' => '56280000', 'street' => 'Avenida Florentino Alves Batista', 'number' => '320', 'neighborhood' => 'Centro', 'city' => 'Araripina', 'state' => 'PE']);

        $this->team();
        $this->contractTemplates();
        app(EnsureDefaultDealStages::class)->handle($this->agency);

        $this->rentals();
        $this->sales();
        $this->funnel();
        $this->showcaseRequests();

        $this->payOutEveryMonth();
        $this->sendMessages();
    }

    /**
     * The team: an administrator, members by branch, a pending invitation and a former member.
     */
    private function team(): void
    {
        $this->team['ana'] = $this->user('Ana Administradora', 'admin@imobizilla.test', Role::Admin);
        $this->team['gustavo'] = $this->user('Gustavo Geral', 'geral@imobizilla.test', Role::General, [$this->matriz, $this->araripina]);
        $this->team['bruno'] = $this->user('Bruno Corretor', 'bruno@imobizilla.test', Role::General, [$this->matriz]);
        $this->team['renata'] = $this->user('Renata Corretora', 'renata@imobizilla.test', Role::General, [$this->araripina]);
        $this->team['carla'] = $this->user('Carla Financeiro', 'carla@imobizilla.test', Role::General, [$this->matriz, $this->araripina]);

        $former = $this->user('Paula Souza', 'paula@imobizilla.test', Role::General, [$this->matriz]);
        $former->update(['deactivated_at' => $this->day(-2, 28)]);

        $invited = $this->agency->users()->create(['name' => 'Diego Martins', 'email' => 'diego@imobizilla.test', 'password' => Str::random(64), 'role' => Role::General]);
        $invited->branches()->attach($this->matriz);
        $this->at($this->today->subDays(2)->toDateString(), fn () => $invited->startInvitation());
    }

    /**
     * The standard templates of every kind, plus a commercial lease the agency wrote from the standard one.
     */
    private function contractTemplates(): void
    {
        foreach (ContractTemplateKind::cases() as $kind) {
            app(EnsureDefaultContractTemplate::class)->handle($this->agency, $kind);
        }

        $lease = $this->template(ContractTemplateKind::Lease);

        $this->agency->contractTemplates()->create([
            'kind' => ContractTemplateKind::Lease,
            'name' => 'Locação comercial',
            'body' => $lease->body,
            'is_default' => false,
        ]);
    }

    /**
     * Owners, their properties and the leases with all their history.
     */
    private function rentals(): void
    {
        $account = $this->agency;

        $antonio = $this->owner($account, 'Antônio Ferreira Lima', '41122233344', ['pix_key' => 'antonio.ferreira@email.com', 'rg' => '4.123.456 SDS/PE', 'marital_status' => MaritalStatus::Married, 'profession' => 'Comerciante', 'street' => 'Rua Coronel Araújo', 'number' => '90']);
        $maria = $this->owner($account, 'Maria das Graças Silva', '52233344455', ['pix_key' => '87999990010', 'rg' => '2.987.654 SDS/PE', 'marital_status' => MaritalStatus::Widowed, 'profession' => 'Aposentada', 'street' => 'Rua São José', 'number' => '12']);
        $joao = $this->owner($account, 'João Silva Neto', '63344455566', ['pix_key' => 'joao.neto@email.com', 'marital_status' => MaritalStatus::Single, 'profession' => 'Engenheiro civil', 'street' => 'Rua do Rosário', 'number' => '8']);
        $construtora = $this->owner($account, 'Construtora Horizonte Ltda', '11222333000181', [
            'pix_key' => '11222333000181',
            'street' => 'Avenida Brasil',
            'number' => '500',
            'admin_fee_percent' => 8,
            'intermediation_fee_percent' => 50,
            'payout_schedule' => PayoutSchedule::MonthlyDay,
            'payout_day' => 10,
        ]);
        $pedro = $this->owner($account, 'Pedro Santos Rocha', '85566677788', ['pix_key' => '87999990020', 'marital_status' => MaritalStatus::Married, 'profession' => 'Agricultor', 'city' => 'Araripina', 'zip_code' => '56280000']);
        $francisca = $this->owner($account, 'Francisca Oliveira Bezerra', '96677788899', ['pix_key' => 'francisca.bezerra@email.com', 'marital_status' => MaritalStatus::StableUnion, 'profession' => 'Empresária']);

        // Antônio: an apartment rented for eight months, a house rented with a deposit and a house free.
        $apartment = $this->property($account, $this->matriz, $antonio, [
            'type' => PropertyType::Apartment, 'street' => 'Rua Siqueira Campos', 'number' => '77', 'complement' => 'Apto 102',
            'rent_amount' => 1100, 'bedrooms' => 2, 'suites' => 1, 'bathrooms' => 2, 'parking_spaces' => 1, 'built_area' => 68,
            'amenities' => [PropertyAmenity::AirConditioning, PropertyAmenity::BuiltInWardrobes, PropertyAmenity::Elevator, PropertyAmenity::Concierge],
            'water_account' => '1029384', 'energy_account' => '7001234567', 'registration_number' => '12.345',
        ], createdAt: $this->day(-9, 3));
        $apartmentLease = $this->lease($account, $apartment, $this->tenant($account, 'Fernanda Costa', '11122233396', ['profession' => 'Enfermeira', 'marital_status' => MaritalStatus::Single]), -8, 1100, 10);
        $this->receiveRent($apartmentLease, late: [-4 => 12, 0 => 3]);

        $sink = app(CreateExtraCharge::class)->handle($apartmentLease, ['description' => 'Conserto da pia', 'amount' => 180, 'due_date' => $this->day(-1, 20)]);
        $this->receive($sink, $this->day(-1, 22));
        $copy = app(CreateExtraCharge::class)->handle($apartmentLease, ['description' => 'Segunda via de boleto', 'amount' => 15, 'due_date' => $this->day(-1, 20), 'pass_to_owner' => false]);
        $this->receive($copy, $this->day(-1, 22));

        $this->leaseContract($apartmentLease);
        $this->leaseDocument($apartmentLease, LeaseDocumentType::MoveInInspection, 'Vistoria de entrada.pdf', 'Laudo de vistoria de entrada', $this->inspection($apartment, 'entrada'));

        $this->expense($account, $apartment, ExpenseType::PropertyTax, 'IPTU – parcela do mês passado', 85, $this->day(-1, 15), $this->day(-1, 15));
        $this->expense($account, $apartment, ExpenseType::PropertyTax, 'IPTU – parcela do mês', 85, $this->day(0, 15), null);
        $this->expense($account, $apartment, ExpenseType::CondoFee, 'Condomínio do mês', 320, $this->day(0, 10), $this->day(0, 10));

        $house = $this->property($account, $this->matriz, $antonio, [
            'street' => 'Rua Joaquim Nabuco', 'number' => '210', 'rent_amount' => 1300,
            'bedrooms' => 3, 'suites' => 1, 'bathrooms' => 2, 'parking_spaces' => 1, 'built_area' => 110, 'land_area' => 200,
            'amenities' => [PropertyAmenity::Backyard, PropertyAmenity::ServiceArea, PropertyAmenity::ElectricGate],
            'water_account' => '1029385', 'energy_account' => '7001234568',
        ], createdAt: $this->day(-4, 10));
        $ricardo = $this->tenant($account, 'Ricardo Gomes', '22233344405', ['profession' => 'Vendedor', 'marital_status' => MaritalStatus::Married]);
        $houseLease = $this->lease($account, $house, $ricardo, -3, 1300, 5, [
            'guarantee_type' => GuaranteeType::Deposit,
            'deposit_amount' => 3900,
        ]);
        $this->receiveRent($houseLease);
        app(CreateExtraCharge::class)->handle($houseLease, ['description' => 'Cópia das chaves', 'amount' => 35, 'due_date' => $this->today->addDays(5)->toDateString(), 'pass_to_owner' => false]);
        $this->leaseContract($houseLease);
        $this->expense($account, $house, ExpenseType::Maintenance, 'Vistoria de entrada', 150, $this->day(-3, 2), $this->day(-3, 2), ExpensePayer::Agency);

        $this->property($account, $this->matriz, $antonio, [
            'street' => 'Rua Pernambuco', 'number' => '15', 'rent_amount' => 900,
            'bedrooms' => 2, 'bathrooms' => 1, 'parking_spaces' => 1, 'built_area' => 70, 'land_area' => 150,
            'description' => "Casa simples e bem conservada a duas quadras da praça principal. Sala, cozinha, dois quartos, banheiro social e área de serviço coberta.\n\nGaragem para um carro. Aluguel com fiador, caução ou seguro-fiança.",
            'amenities' => [PropertyAmenity::ServiceArea, PropertyAmenity::PetsAllowed],
        ], photos: ['casa-d', 'sala-c', 'cozinha-a'], createdAt: $this->day(-2, 5));

        $this->property($account, $this->matriz, $antonio, [
            'type' => PropertyType::Farm, 'street' => 'Estrada do Sítio Boa Esperança', 'number' => 's/n', 'neighborhood' => 'Zona rural',
            'for_rent' => false, 'rent_amount' => null, 'for_sale' => true, 'sale_price' => 450000, 'land_area' => 120000,
            'amenities' => [PropertyAmenity::Well, PropertyAmenity::WaterTank, PropertyAmenity::SolarEnergy],
        ], createdAt: $this->day(-6, 8));

        // Maria (60%) and João (40%) inherited a house together; the lease has a guarantor and its first
        // anniversary is coming, so the rent adjustment is due.
        $inherited = $this->property($account, $this->matriz, $maria, [
            'street' => 'Rua São José', 'number' => '150', 'rent_amount' => 2000,
            'bedrooms' => 4, 'suites' => 2, 'bathrooms' => 3, 'parking_spaces' => 2, 'built_area' => 180, 'land_area' => 360,
            'amenities' => [PropertyAmenity::Backyard, PropertyAmenity::Barbecue, PropertyAmenity::ServiceArea],
            'water_account' => '1029386', 'energy_account' => '7001234569', 'registration_number' => '8.877',
        ], createdAt: $this->day(-12, 2));
        app(SyncPropertyOwners::class)->handle($inherited, [$joao->id => 40.0]);
        $marcos = $this->tenant($account, 'Marcos Vieira', '33344455514', ['profession' => 'Gerente de banco', 'marital_status' => MaritalStatus::Married]);
        $inheritedLease = $this->lease($account, $inherited, $marcos, -11, 2000, 10, ['guarantee_type' => GuaranteeType::Guarantor], [
            'name' => 'Raimundo Vieira',
            'cpf_cnpj' => '12312312387',
            'rg' => '1.234.567 SDS/PE',
            'nationality' => 'brasileiro',
            'marital_status' => MaritalStatus::Married,
            'profession' => 'Comerciante',
            'phone' => '87999990040',
            'zip_code' => '56200000',
            'street' => 'Rua Coronel Modesto',
            'number' => '44',
            'neighborhood' => 'Centro',
            'city' => 'Ouricuri',
            'state' => 'PE',
            'spouse_name' => 'Helena Vieira',
            'property_registration' => 'Matrícula 5.432 do Cartório de Ouricuri',
        ]);
        $this->receiveRent($inheritedLease, late: [-7 => 4], unpaid: [-1, 0]);
        $this->receive($this->rentOf($inheritedLease, -1), $this->day(-1, 25), amount: 1000);
        $this->leaseContract($inheritedLease);
        $this->leaseDocument($inheritedLease, LeaseDocumentType::TenantDocument, 'Comprovante de renda.pdf', 'Comprovante de renda', '<p>Declaração de renda de Marcos Vieira, gerente de banco, com renda mensal de R$ 9.500,00.</p>');

        $roof = $this->expense($account, $inherited, ExpenseType::Maintenance, 'Conserto do telhado', 600, $this->day(-1, 3), $this->day(-1, 3), providerName: 'José Telhados', providerDocument: '55566677788');
        $this->expenseReceipt($roof);
        $this->expenseDocument($roof, ExpenseDocumentType::Photo, 'Telhado consertado.jpg', 'casa-c');

        $this->property($account, $this->matriz, $joao, [
            'type' => PropertyType::Apartment, 'street' => 'Rua do Rosário', 'number' => '33', 'complement' => 'Apto 401 – Edifício Solar',
            'rent_amount' => 1250, 'bedrooms' => 2, 'suites' => 1, 'bathrooms' => 2, 'parking_spaces' => 1, 'built_area' => 72,
            'description' => "Apartamento no 4º andar com varanda e vista para a serra. Sala para dois ambientes, cozinha americana e dois quartos, um deles suíte.\n\nCondomínio com piscina, academia e salão de festas. Portaria 24 horas.",
            'amenities' => [PropertyAmenity::Balcony, PropertyAmenity::AirConditioning, PropertyAmenity::Elevator, PropertyAmenity::CondoPool, PropertyAmenity::Gym, PropertyAmenity::PartyRoom, PropertyAmenity::Concierge],
        ], photos: ['sala-a', 'cozinha-a', 'sala-b'], createdAt: $this->day(-1, 12));

        $this->property($account, $this->matriz, $maria, [
            'street' => 'Rua Padre Cícero', 'number' => '501', 'neighborhood' => 'São Sebastião',
            'rent_amount' => 1500, 'for_sale' => true, 'sale_price' => 320000, 'is_featured' => true,
            'bedrooms' => 3, 'suites' => 2, 'bathrooms' => 3, 'parking_spaces' => 2, 'built_area' => 160, 'land_area' => 300, 'land_front' => 10, 'land_depth' => 30,
            'description' => "Casa térrea numa rua tranquila do São Sebastião, com sala ampla integrada à cozinha e portas de vidro que abrem para a área da piscina. São três quartos, dois deles suítes, e um banheiro social.\n\nO terreno tem 10 × 30 m, com garagem coberta para dois carros e quintal nos fundos. Disponível para venda ou para aluguel.",
            'amenities' => [PropertyAmenity::Pool, PropertyAmenity::GourmetArea, PropertyAmenity::ElectricGate, PropertyAmenity::Backyard, PropertyAmenity::BuiltInWardrobes],
        ], photos: ['casa-a', 'sala-a', 'cozinha-b', 'sala-e', 'sala-b'], createdAt: $this->day(-3, 20));

        $this->property($account, $this->matriz, $maria, [
            'type' => PropertyType::Commercial, 'street' => 'Avenida Ulisses Guimarães', 'number' => '1020', 'rent_amount' => 2500,
            'bathrooms' => 1, 'built_area' => 120, 'status' => PropertyStatus::Maintenance,
        ], createdAt: $this->day(-5, 2));

        // The building company: 8% admin fee and payouts on the 10th. An office rented on a commercial
        // lease with surety insurance, which ends this month; houses for sale.
        $office = $this->property($account, $this->matriz, $construtora, [
            'type' => PropertyType::Office, 'street' => 'Avenida Brasil', 'number' => '500', 'complement' => 'Sala 3',
            'rent_amount' => 1800, 'bathrooms' => 1, 'parking_spaces' => 1, 'built_area' => 45,
            'amenities' => [PropertyAmenity::AirConditioning, PropertyAmenity::SecurityCameras],
        ], createdAt: $this->day(-12, 1));
        $officeLease = $this->lease($account, $office, $this->tenant($account, 'Contabilidade Exata Ltda', '44555666000172'), -11, 1800, min(28, $this->today->day), [
            'purpose' => LeasePurpose::Commercial,
            'end_date' => $this->month(1)->subDay()->toDateString(),
            'guarantee_type' => GuaranteeType::SuretyBond,
            'surety_insurer' => 'Porto Seguro',
            'surety_policy_number' => '0775.12.345678-9',
        ]);
        $this->receiveRent($officeLease, unpaid: [0]);
        $this->expense($account, $office, ExpenseType::CondoFee, 'Condomínio do mês', 450, $this->day(0, 20), null);

        // Pedro: a lease renewed last month, with its rent adjusted by the IGP-M, a 12% admin fee and
        // late charges split in half; and a house whose lease ended, with the deposit settled.
        $renewed = $this->property($account, $this->araripina, $pedro, [
            'type' => PropertyType::Apartment, 'street' => 'Rua Sete de Setembro', 'number' => '55', 'complement' => 'Apto 201',
            'city' => 'Araripina', 'zip_code' => '56280000', 'rent_amount' => 1200,
            'bedrooms' => 2, 'bathrooms' => 1, 'parking_spaces' => 1, 'built_area' => 60,
        ], createdAt: $this->day(-14, 10));
        $camila = $this->tenant($account, 'Camila Ribeiro', '66677788832', ['profession' => 'Advogada', 'marital_status' => MaritalStatus::Single]);
        $renewedLease = $this->lease($account, $renewed, $camila, -13, 1200, min(28, $this->today->day + 2), [
            'end_date' => $this->month(-1)->subDay()->toDateString(),
            'admin_fee_percent' => 12,
            'late_charges_owner_percent' => 50,
            'adjustment_index' => AdjustmentIndex::Igpm,
        ]);
        $renewal = $this->at($this->day(-2, 20), fn () => app(RenewLease::class)->handle($renewedLease, $this->month(11)->subDay(), 'Renovado por mais 12 meses.'));
        $this->at($this->day(-2, 25), fn () => app(ApplyLeaseAdjustment::class)->handle($renewedLease->refresh(), ['percent' => 4.2], 'IGP-M acumulado dos últimos 12 meses.'));
        $this->receiveRent($renewedLease, late: [-9 => 8, -2 => 3]);
        $this->leaseContract($renewedLease);
        $this->leaseDocument($renewedLease, LeaseDocumentType::Amendment, 'Aditivo de renovação.pdf', 'Aditivo de renovação', app(RenderLeaseRenewalAmendment::class)->handle($renewal));

        $finished = $this->property($account, $this->araripina, $pedro, [
            'street' => 'Rua Bom Jesus', 'number' => '19', 'neighborhood' => 'Centro', 'city' => 'Araripina', 'zip_code' => '56280000',
            'rent_amount' => 1150, 'bedrooms' => 3, 'bathrooms' => 2, 'parking_spaces' => 1, 'built_area' => 105, 'land_area' => 200,
            'description' => "Casa ampla e arejada, recém-pintada, no Centro de Araripina. Sala, cozinha com despensa, três quartos e dois banheiros.\n\nQuintal com área de serviço e garagem para um carro.",
            'amenities' => [PropertyAmenity::Backyard, PropertyAmenity::ServiceArea],
        ], createdAt: $this->day(-15, 5));
        $larissa = $this->tenant($account, 'Larissa Nunes', '88899900069', ['profession' => 'Dentista', 'marital_status' => MaritalStatus::Single]);
        $finishedLease = $this->lease($account, $finished, $larissa, -14, 1150, 10, [
            'end_date' => $this->month(-2)->endOfMonth()->toDateString(),
            'guarantee_type' => GuaranteeType::Deposit,
            'deposit_amount' => 3450,
        ]);
        $this->receiveRent($finishedLease);
        $this->at($this->day(-1, 2), fn () => app(FinishLease::class)->handle($finishedLease->refresh(), LeaseStatus::Ended));
        $this->at($this->day(-1, 5), fn () => app(SettleLeaseDeposit::class)->handle($finishedLease->refresh(), [], [
            ['description' => 'Pintura da casa', 'amount' => 650],
            ['description' => 'Limpeza final', 'amount' => 250],
        ], CarbonImmutable::parse($this->day(-1, 5))));
        $this->leaseDocument($finishedLease, LeaseDocumentType::MoveOutInspection, 'Vistoria de saída.pdf', 'Laudo de vistoria de saída', $this->inspection($finished, 'saída'));
        $this->publish($finished, ['casa-e', 'sala-e', 'cozinha-b'], $this->day(-1, 6));

        $this->property($account, $this->araripina, $pedro, [
            'type' => PropertyType::Warehouse, 'street' => 'Rodovia BR-316', 'number' => 'km 2', 'neighborhood' => 'Distrito Industrial',
            'city' => 'Araripina', 'zip_code' => '56280000', 'rent_amount' => 3500, 'built_area' => 600, 'land_area' => 1500,
        ], createdAt: $this->day(-7, 9));

        $shower = $this->expense($account, $renewed, ExpenseType::Maintenance, 'Troca do chuveiro elétrico', 180, $this->day(-2, 8), $this->day(-2, 8), providerName: 'Eletro Araripina', providerDocument: '22333444000155');
        $this->expenseDocument($shower, ExpenseDocumentType::Invoice, 'Nota fiscal.pdf', null, '<p>Nota fiscal de serviço nº 1.284 – Eletro Araripina. Troca de chuveiro elétrico, peça e mão de obra: R$ 180,00.</p>');

        // Francisca: houses for rent on the showcase.
        $this->property($account, $this->matriz, $francisca, [
            'street' => 'Rua do Mirante', 'number' => '19', 'neighborhood' => 'Alto da Boa Vista', 'rent_amount' => 1150,
            'bedrooms' => 3, 'bathrooms' => 2, 'parking_spaces' => 1, 'built_area' => 105, 'land_area' => 200,
            'description' => "Casa no Alto da Boa Vista com sala integrada à cozinha e bastante luz natural. Três quartos, dois banheiros e uma vaga.\n\nBairro residencial e tranquilo, a poucos minutos do Centro. Aceita animais.",
            'amenities' => [PropertyAmenity::PetsAllowed, PropertyAmenity::Backyard],
        ], photos: ['sala-d', 'sala-e', 'cozinha-b'], createdAt: $this->today->subDays(4)->toDateString());

        $this->property($account, $this->matriz, $francisca, [
            'street' => 'Rua Sete de Setembro', 'number' => '300', 'rent_amount' => 1300,
            'bedrooms' => 3, 'bathrooms' => 2, 'parking_spaces' => 1, 'built_area' => 110, 'land_area' => 200,
            'description' => "Casa com piscina e área de lazer nos fundos, no Centro. Três quartos, dois banheiros e uma vaga de garagem.\n\nBoa para família. Aluguel com caução de três meses ou fiador.",
            'amenities' => [PropertyAmenity::Pool, PropertyAmenity::Barbecue, PropertyAmenity::GourmetArea],
        ], photos: ['casa-b', 'sala-b', 'cozinha-a'], createdAt: $this->day(-1, 25));

        $this->property($account, $this->matriz, $francisca, [
            'street' => 'Rua Coronel Araújo', 'number' => '57', 'rent_amount' => 1500,
            'bedrooms' => 3, 'suites' => 1, 'bathrooms' => 2, 'parking_spaces' => 2, 'built_area' => 130, 'land_area' => 250,
            'description' => "Casa com varanda na frente e garagem para dois carros, numa rua arborizada do Centro. Três quartos, um suíte, e cozinha ampla.\n\nAluguel com fiador, caução ou seguro-fiança.",
            'amenities' => [PropertyAmenity::Balcony, PropertyAmenity::ElectricGate, PropertyAmenity::SolarEnergy],
        ], photos: ['casa-g', 'sala-c', 'cozinha-a'], createdAt: $this->today->subDays(2)->toDateString());

        $this->owners = compact('antonio', 'maria', 'joao', 'construtora', 'pedro', 'francisca');
    }

    /**
     * Two sales: a lot whose down payment the agency received for the seller, and a house sold on financing.
     */
    private function sales(): void
    {
        $account = $this->agency;
        $construtora = $this->owners['construtora'];
        $francisca = $this->owners['francisca'];

        $lot = $this->property($account, $this->matriz, $construtora, [
            'type' => PropertyType::Land, 'street' => 'Rua Projetada 4', 'number' => 'Lote 12', 'neighborhood' => 'Horizonte',
            'for_rent' => false, 'rent_amount' => null, 'for_sale' => true, 'sale_price' => 95000,
            'land_area' => 300, 'land_front' => 10, 'land_depth' => 30,
        ], createdAt: $this->day(-4, 1));

        $juliana = $this->lead('Juliana Rocha', LeadInterest::Buy, LeadSource::WalkIn, 100000, $this->team['bruno'], $this->day(-2, 10), ['payment_plan' => PaymentPlan::Cash]);
        $lotDeal = $this->deal($juliana, $lot, 'Documentação', DealType::Sale, 95000, $this->team['bruno'], $this->day(-2, 10));
        $this->visit($lotDeal, $this->day(-2, 14).' 09:00:00', VisitStatus::Done, $this->team['bruno'], 'Gostou da localização, quer construir logo.');
        $this->proposal($lotDeal, 92000, PaymentPlan::Cash, 'À vista, com sinal de R$ 10.000.', ProposalStatus::Rejected, $this->day(-2, 18));
        $this->proposal($lotDeal, 95000, PaymentPlan::Cash, 'Preço cheio, sinal de R$ 10.000 e o restante na escritura.', ProposalStatus::Accepted, $this->day(-2, 22));

        $lotSale = $this->at($this->day(-1, 2), fn (): Sale => app(RegisterSale::class)->handle($lot, [
            'buyer_name' => 'Juliana Rocha',
            'buyer_document' => '77788899940',
            'buyer_phone' => '87999990030',
            'buyer_email' => 'juliana.rocha@email.com',
            'buyer_rg' => '7.654.321 SDS/PE',
            'buyer_nationality' => 'brasileira',
            'buyer_marital_status' => MaritalStatus::Married,
            'buyer_profession' => 'Arquiteta',
            'buyer_zip_code' => '56200000',
            'buyer_street' => 'Rua das Acácias',
            'buyer_number' => '88',
            'buyer_neighborhood' => 'Horizonte',
            'buyer_city' => 'Ouricuri',
            'buyer_state' => 'PE',
            'price' => 95000,
            'sold_on' => $this->day(-1, 2),
            'payment_plan' => PaymentPlan::Cash,
            'down_payment' => 10000,
            'down_payment_received_by_agency' => true,
            'deed_deadline' => $this->month(1)->endOfMonth()->toDateString(),
            'commission_percent' => 6,
            'user_id' => $this->team['bruno']->id,
            'notes' => 'Sinal pago por Pix na imobiliária; escritura no mês que vem.',
        ], $lotDeal));
        $this->saleDocument($lotSale, ContractTemplateKind::SaleContract, SaleDocumentType::SignedContract, 'Contrato de compra e venda assinado.pdf');
        $this->saleDocument($lotSale, ContractTemplateKind::DownPaymentReceipt, SaleDocumentType::DownPaymentReceipt, 'Recibo de sinal.pdf');

        $sold = $this->property($account, $this->araripina, $francisca, [
            'street' => 'Rua Padre Cícero', 'number' => '80', 'city' => 'Araripina', 'zip_code' => '56280000',
            'for_rent' => false, 'rent_amount' => null, 'for_sale' => true, 'sale_price' => 240000,
            'bedrooms' => 3, 'suites' => 1, 'bathrooms' => 2, 'parking_spaces' => 2, 'built_area' => 120, 'land_area' => 250,
        ], createdAt: $this->day(-6, 1));
        $henrique = $this->lead('Henrique Barros', LeadInterest::Buy, LeadSource::Portal, 250000, $this->team['renata'], $this->day(-4, 3), ['payment_plan' => PaymentPlan::Financing]);
        $soldDeal = $this->deal($henrique, $sold, 'Documentação', DealType::Sale, 240000, $this->team['renata'], $this->day(-4, 3));
        $this->visit($soldDeal, $this->day(-4, 8).' 15:00:00', VisitStatus::Done, $this->team['renata'], 'Casal com dois filhos, adorou o quintal.');
        $this->proposal($soldDeal, 240000, PaymentPlan::Financing, 'Financiamento pela Caixa com entrada de 20%.', ProposalStatus::Accepted, $this->day(-4, 12));

        $this->at($this->day(-2, 12), fn (): Sale => app(RegisterSale::class)->handle($sold, [
            'buyer_name' => 'Henrique Barros',
            'buyer_document' => '31231231200',
            'buyer_phone' => '87999990050',
            'buyer_email' => 'henrique.barros@email.com',
            'buyer_marital_status' => MaritalStatus::Married,
            'buyer_profession' => 'Professor',
            'price' => 240000,
            'sold_on' => $this->day(-2, 12),
            'payment_plan' => PaymentPlan::Financing,
            'down_payment' => 48000,
            'down_payment_received_by_agency' => false,
            'deed_deadline' => $this->day(-1, 30),
            'commission_percent' => 5,
            'user_id' => $this->team['renata']->id,
            'notes' => 'Financiamento aprovado pela Caixa.',
        ], $soldDeal));
    }

    /**
     * Leads from every source and deals at every stage, with visits for today and the coming days.
     */
    private function funnel(): void
    {
        $bruno = $this->team['bruno'];
        $renata = $this->team['renata'];
        $properties = Property::withoutGlobalScopes()->where('account_id', $this->agency->id)->get()->keyBy('street');

        $padreCicero = $properties->first(fn (Property $property): bool => $property->street === 'Rua Padre Cícero' && $property->number === '501');
        $pernambuco = $properties->get('Rua Pernambuco');
        $mirante = $properties->get('Rua do Mirante');
        $pool = $properties->first(fn (Property $property): bool => $property->street === 'Rua Sete de Setembro' && $property->number === '300');
        $rosario = $properties->get('Rua do Rosário');
        $bomJesus = $properties->get('Rua Bom Jesus');
        $farm = $properties->get('Estrada do Sítio Boa Esperança');
        $house = $properties->get('Rua Joaquim Nabuco');

        $horizonte = $this->property($this->agency, $this->matriz, $this->owners['construtora'], [
            'street' => 'Rua Horizonte', 'number' => '27', 'neighborhood' => 'Horizonte',
            'for_rent' => false, 'rent_amount' => null, 'for_sale' => true, 'sale_price' => 295000, 'is_featured' => true,
            'bedrooms' => 3, 'suites' => 1, 'bathrooms' => 2, 'parking_spaces' => 2, 'built_area' => 150, 'land_area' => 300, 'land_front' => 10, 'land_depth' => 30,
            'description' => "Casa de dois pavimentos com terraço no andar de cima e vista aberta para o bairro. Embaixo ficam a sala, a cozinha e um lavabo. Em cima, os três quartos.\n\nGaragem para dois carros e terreno de 10 × 30 m. Documentação em dia e aceita financiamento bancário.",
            'amenities' => [PropertyAmenity::Balcony, PropertyAmenity::GourmetArea, PropertyAmenity::ElectricGate],
        ], photos: ['casa-f', 'sala-c', 'cozinha-b', 'sala-e'], createdAt: $this->day(-3, 1));

        $this->property($this->agency, $this->matriz, $this->owners['construtora'], [
            'street' => 'Rua Projetada A', 'number' => '12', 'neighborhood' => 'Horizonte',
            'for_rent' => false, 'rent_amount' => null, 'for_sale' => true, 'sale_price' => 210000,
            'bedrooms' => 3, 'suites' => 1, 'bathrooms' => 2, 'parking_spaces' => 2, 'built_area' => 120, 'land_area' => 250,
            'description' => "Casa nova, em rua calçada do Horizonte. Piso em porcelanato, cozinha americana e área de serviço separada.\n\nTrês quartos, um deles suíte, e garagem para dois carros. Aceita financiamento bancário.",
            'amenities' => [PropertyAmenity::ServiceArea, PropertyAmenity::SolarEnergy],
        ], photos: ['casa-d', 'sala-d', 'cozinha-a'], createdAt: $this->today->subDays(6)->toDateString());

        // New contacts.
        $roberto = $this->lead('Roberto Alves', LeadInterest::Rent, LeadSource::WhatsApp, 1000, $bruno, $this->today->subDays(1)->toDateString());
        $this->deal($roberto, $pernambuco, 'Novo contato', DealType::Rent, 900, $bruno, $this->today->subDays(1)->toDateString());

        // Visits today and in the coming days, for the agenda.
        $carlos = $this->lead('Carlos Mendonça', LeadInterest::Buy, LeadSource::Instagram, 320000, $bruno, $this->today->subDays(9)->toDateString(), ['payment_plan' => PaymentPlan::Financing]);
        $carlosDeal = $this->deal($carlos, $horizonte, 'Visita', DealType::Sale, 295000, $bruno, $this->today->subDays(9)->toDateString());
        $this->visit($carlosDeal, $this->today->addDay()->setTime(10, 0)->toDateTimeString(), VisitStatus::Scheduled, $bruno);

        $patricia = $this->lead('Patrícia Gomes', LeadInterest::Rent, LeadSource::Portal, 1400, $bruno, $this->today->subDays(3)->toDateString());
        $patriciaDeal = $this->deal($patricia, $pool, 'Visita', DealType::Rent, 1300, $bruno, $this->today->subDays(3)->toDateString());
        $this->visit($patriciaDeal, $this->today->setTime(16, 0)->toDateTimeString(), VisitStatus::Scheduled, $bruno);

        $debora = $this->lead('Débora Lins', LeadInterest::Rent, LeadSource::WhatsApp, 1200, $renata, $this->today->subDays(2)->toDateString(), ['branch_id' => $this->araripina->id]);
        $deboraDeal = $this->deal($debora, $bomJesus, 'Visita', DealType::Rent, 1150, $renata, $this->today->subDays(2)->toDateString());
        $this->visit($deboraDeal, $this->today->addDays(2)->setTime(9, 30)->toDateTimeString(), VisitStatus::Scheduled, $renata);
        $this->visit($deboraDeal, $this->today->subDay()->setTime(17, 0)->toDateTimeString(), VisitStatus::Canceled, $renata, 'Remarcou por causa do trabalho.');

        // Proposals.
        $fernanda = $this->lead('Fernanda Lima', LeadInterest::Buy, LeadSource::Portal, 290000, $bruno, $this->day(-1, 20), ['payment_plan' => PaymentPlan::Financing]);
        $fernandaDeal = $this->deal($fernanda, $horizonte, 'Proposta', DealType::Sale, 280000, $bruno, $this->day(-1, 20));
        $this->visit($fernandaDeal, $this->today->subDays(10)->setTime(15, 0)->toDateTimeString(), VisitStatus::Done, $bruno, 'Gostou muito da cozinha e do terraço.');
        $this->proposal($fernandaDeal, 280000, PaymentPlan::Financing, 'Financiamento pela Caixa, entrada de 20%.', ProposalStatus::Pending, $this->today->subDays(6)->toDateString());

        $gabriel = $this->lead('Gabriel Torres', LeadInterest::Buy, LeadSource::Referral, 330000, $bruno, $this->day(-1, 8));
        $gabrielDeal = $this->deal($gabriel, $padreCicero, 'Proposta', DealType::Sale, 310000, $bruno, $this->day(-1, 8));
        $this->visit($gabrielDeal, $this->day(-1, 15).' 10:00:00', VisitStatus::Done, $bruno, 'Quer a casa, mas acha o preço alto.');
        $this->proposal($gabrielDeal, 290000, PaymentPlan::Cash, 'À vista.', ProposalStatus::Countered, $this->today->subDays(12)->toDateString());
        $this->proposal($gabrielDeal, 310000, PaymentPlan::Cash, 'Contraproposta da proprietária, à vista.', ProposalStatus::Pending, $this->today->subDays(4)->toDateString());

        // Paperwork.
        $juliana = $this->lead('Juliana Prado', LeadInterest::Rent, LeadSource::Facebook, 1300, $bruno, $this->today->subDays(15)->toDateString());
        $julianaDeal = $this->deal($juliana, $rosario, 'Documentação', DealType::Rent, 1250, $bruno, $this->today->subDays(15)->toDateString());
        $this->visit($julianaDeal, $this->today->subDays(8)->setTime(11, 0)->toDateTimeString(), VisitStatus::Done, $bruno, 'Vai mandar os documentos e o fiador.');

        // Won and lost.
        $ricardo = $this->lead('Ricardo Gomes', LeadInterest::Rent, LeadSource::Sign, 1400, $bruno, $this->day(-4, 12));
        $ricardoDeal = $this->deal($ricardo, $house, 'Documentação', DealType::Rent, 1300, $bruno, $this->day(-4, 12));
        $this->close($ricardoDeal, 'Fechado', $this->day(-3, 1));

        $sandra = $this->lead('Sandra Moura', LeadInterest::Buy, LeadSource::Referral, 150000, $bruno, $this->day(-2, 3));
        $sandraDeal = $this->deal($sandra, $farm, 'Visita', DealType::Sale, 450000, $bruno, $this->day(-2, 3));
        $this->close($sandraDeal, 'Perdido', $this->day(-2, 20), 'Escolheu um imóvel em outra cidade.');

        $marcelo = $this->lead('Marcelo Dias', LeadInterest::Rent, LeadSource::Instagram, 1000, $bruno, $this->today->subDays(20)->toDateString());
        $marceloDeal = $this->deal($marcelo, $mirante, 'Novo contato', DealType::Rent, 1150, $bruno, $this->today->subDays(20)->toDateString());
        $this->close($marceloDeal, 'Perdido', $this->today->subDays(5)->toDateString(), 'Achou o aluguel alto para o orçamento.');

        // Leads still without a deal, one of them not assigned to anyone yet.
        $this->lead('Lucas Ferreira', LeadInterest::Buy, LeadSource::Website, 200000, null, $this->today->toDateString(), ['notes' => 'Procura casa de 3 quartos no Horizonte.']);
        $this->lead('Tânia Ribeiro', LeadInterest::Rent, LeadSource::WalkIn, 900, $renata, $this->today->subDays(7)->toDateString(), ['branch_id' => $this->araripina->id, 'property_type' => PropertyType::Apartment]);
    }

    /**
     * Requests sent by visitors of the showcase: visits to properties and owners who want to list.
     */
    private function showcaseRequests(): void
    {
        $published = Property::withoutGlobalScopes()->where('account_id', $this->agency->id)->whereNotNull('published_at')->get();
        $padreCicero = $published->first(fn (Property $property): bool => $property->street === 'Rua Padre Cícero');
        $mirante = $published->first(fn (Property $property): bool => $property->street === 'Rua do Mirante');

        $this->at($this->today->subDays(1)->setTime(20, 15)->toDateTimeString(), fn () => app(RegisterShowcaseVisit::class)->handle($padreCicero, [
            'name' => 'Ana Souza', 'phone' => '87999112233', 'period' => 'pela manhã', 'purpose' => 'rent',
        ]));
        $this->at($this->today->subDays(3)->setTime(13, 40)->toDateTimeString(), fn () => app(RegisterShowcaseVisit::class)->handle($mirante, [
            'name' => 'Rafael Mendes', 'phone' => '87999445566', 'period' => 'no sábado', 'purpose' => 'rent',
        ]));
        $this->at($this->today->setTime(8, 5)->toDateTimeString(), fn () => app(RegisterShowcaseListing::class)->handle($this->agency, [
            'name' => 'José Ribamar', 'phone' => '87999778899', 'goal' => 'vender', 'neighborhood' => 'Centro', 'message' => 'Casa de 3 quartos, desocupada, perto da praça.',
        ]));
        $this->at($this->today->subDays(2)->setTime(18, 30)->toDateTimeString(), fn () => app(RegisterShowcaseListing::class)->handle($this->agency, [
            'name' => 'Cláudia Menezes', 'phone' => '87999001122', 'goal' => 'alugar', 'neighborhood' => 'São Sebastião', 'message' => null,
        ]));
    }

    /**
     * Pay every owner what was theirs on the 16th of each past month, with the proof of transfer on the
     * last two; this month's money is still to be paid out.
     */
    private function payOutEveryMonth(): void
    {
        foreach (range(-12, -1) as $offset) {
            foreach ($this->owners as $owner) {
                $payout = $this->payOut($owner, $this->day($offset, 16));

                if ($payout !== null && $offset >= -2) {
                    $this->payoutProof($payout);
                }
            }
        }
    }

    /**
     * The WhatsApp messages the team sent: rent reminders and overdue notices, payout notices and the
     * rent adjustment notice.
     */
    private function sendMessages(): void
    {
        $compose = app(ComposeWhatsAppMessage::class);
        $carla = $this->team['carla'];

        $open = Payment::withoutGlobalScopes()
            ->where('account_id', $this->agency->id)
            ->whereIn('status', [PaymentStatus::Pending, PaymentStatus::Partial])
            ->whereDate('due_date', '<=', $this->today->addDays(3))
            ->with('lease.tenant', 'lease.property')
            ->get();

        foreach ($open as $payment) {
            $sentAt = $payment->due_date->lt($this->today) ? $this->today->subDay()->setTime(9, 30) : $this->today->setTime(8, 45);

            $this->at($sentAt->toDateTimeString(), function () use ($compose, $payment, $carla): void {
                $this->record($payment, $compose->forPayment($payment), $carla);
            });
        }

        $payouts = OwnerPayout::withoutGlobalScopes()
            ->where('account_id', $this->agency->id)
            ->whereDate('paid_on', $this->day(-1, 16))
            ->get();

        foreach ($payouts as $payout) {
            $this->at($this->day(-1, 16), fn () => $this->record($payout, $compose->forPayout($payout), $carla));
        }

        foreach (LeaseAdjustment::query()->with('lease')->get() as $adjustment) {
            $this->at($this->day(-2, 25), fn () => $this->record($adjustment, $compose->forAdjustment($adjustment), $carla));
        }
    }

    /**
     * Keep a message as sent, as the WhatsApp button does.
     *
     * @param  array{template: string, phone: string|null, text: string}|null  $message
     */
    private function record(Payment|OwnerPayout|LeaseAdjustment $subject, ?array $message, User $user): void
    {
        if ($message === null || $message['phone'] === null) {
            return;
        }

        $subject->sentMessages()->create([
            'account_id' => $this->agency->id,
            'user_id' => $user->id,
            'template' => $message['template'],
            'phone' => $message['phone'],
            'body' => $message['text'],
        ]);
    }

    /**
     * Pay the owner what is theirs to receive on the given date, if anything.
     */
    private function payOut(Owner $owner, string $paidOn): ?OwnerPayout
    {
        return $this->at($paidOn, function () use ($owner, $paidOn): ?OwnerPayout {
            $available = app(SummarizeOwnerBalances::class)->balances([$owner->id])[$owner->id]['available'] ?? 0.0;

            if ($available <= 0) {
                return null;
            }

            return app(RegisterOwnerPayout::class)->handle(
                $owner,
                ['amount' => $available, 'paid_on' => $paidOn, 'payment_method' => PaymentMethod::Pix->value],
                $this->team['carla'],
            );
        });
    }

    private function payoutProof(OwnerPayout $payout): void
    {
        $owner = $payout->owner;
        $amount = number_format((float) $payout->amount, 2, ',', '.');
        $path = $this->storePdf("payouts/{$payout->id}", 'Comprovante de transferência Pix', "<p><strong>Comprovante de transferência Pix</strong></p><p>Pagador: Imobizilla Negócios Imobiliários Ltda</p><p>Recebedor: {$owner->name}</p><p>Chave Pix: {$owner->pix_key}</p><p>Valor: R$ {$amount}</p><p>Data: {$payout->paid_on->format('d/m/Y')}</p>");

        $payout->update(['receipt_name' => 'Comprovante Pix.pdf', 'receipt_path' => $path]);
    }

    /**
     * Receive every rent of the lease already due, on the due date or the given days late, except the
     * months left unpaid. Months are counted from this month: -1 is last month.
     *
     * @param  array<int, int>  $late  Days late, by month.
     * @param  list<int>  $unpaid
     */
    private function receiveRent(Lease $lease, array $late = [], array $unpaid = []): void
    {
        app(SyncLeasePayments::class)->handle($lease->refresh());

        $payments = Payment::withoutGlobalScopes()
            ->where('lease_id', $lease->id)
            ->rent()
            ->where('status', PaymentStatus::Pending)
            ->whereDate('due_date', '<=', $this->today)
            ->orderBy('due_date')
            ->get();

        foreach ($payments as $payment) {
            $offset = (int) round($this->month(0)->diffInMonths(CarbonImmutable::parse($payment->reference_month)->startOfMonth()));

            if (in_array($offset, $unpaid, true)) {
                continue;
            }

            $paidOn = CarbonImmutable::parse($payment->due_date)->addDays($late[$offset] ?? 0);

            if ($paidOn->gt($this->today)) {
                continue;
            }

            $this->receive($payment, $paidOn->toDateString());
        }
    }

    /**
     * Receive a payment on the given date, with the late charges the lease sets when late.
     */
    private function receive(Payment $payment, string $paidOn, ?float $amount = null): void
    {
        $this->at($paidOn, function () use ($payment, $paidOn, $amount): void {
            $amount ??= (float) $payment->amount;
            $charges = app(CalculateLateCharges::class)->handle($payment->load('lease'), $amount, CarbonImmutable::parse($paidOn));

            app(RegisterReceipt::class)->handle($payment, [
                'amount' => $amount,
                'late_fee_amount' => $charges['late_fee'],
                'interest_amount' => $charges['interest'],
                'date' => $paidOn,
                'payment_method' => PaymentMethod::Pix,
            ]);
        });
    }

    /**
     * Get the rent payment of the lease for the month, counted from this month.
     */
    private function rentOf(Lease $lease, int $offset): Payment
    {
        return Payment::withoutGlobalScopes()
            ->where('lease_id', $lease->id)
            ->rent()
            ->whereDate('reference_month', $this->month($offset)->toDateString())
            ->where('status', '!=', PaymentStatus::Canceled)
            ->firstOrFail();
    }

    /**
     * Create an active lease as it was signed on the first day of the given month, for 30 months unless
     * told otherwise.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>|null  $guarantor
     */
    private function lease(Account $account, Property $property, Tenant $tenant, int $startOffset, float $amount, int $dueDay, array $attributes = [], ?array $guarantor = null): Lease
    {
        $start = $this->month($startOffset);

        return $this->at($start->toDateString(), fn (): Lease => app(CreateLease::class)->handle($account->id, [
            'property_id' => $property->id,
            'tenant_id' => $tenant->id,
            'start_date' => $start->toDateString(),
            'end_date' => $start->addMonths(30)->subDay()->toDateString(),
            'amount' => $amount,
            'due_day' => $dueDay,
            'guarantee_type' => GuaranteeType::None,
            ...$attributes,
        ], $guarantor))->refresh();
    }

    /**
     * Keep the signed contract of the lease, written from the agency's lease template.
     */
    private function leaseContract(Lease $lease): void
    {
        $template = $this->template(ContractTemplateKind::Lease, $lease->account_id);

        $this->leaseDocument($lease, LeaseDocumentType::SignedContract, 'Contrato assinado.pdf', 'Contrato de locação', app(RenderLeaseContract::class)->handle($template, $lease));
    }

    private function leaseDocument(Lease $lease, LeaseDocumentType $type, string $name, string $title, string $body): void
    {
        $path = $this->storePdf("leases/{$lease->id}", $title, $body);

        LeaseDocument::create([
            'account_id' => $lease->account_id,
            'lease_id' => $lease->id,
            'type' => $type,
            'name' => $name,
            'path' => $path,
            'mime_type' => 'application/pdf',
            'size' => Storage::disk(LeaseDocument::DISK)->size($path),
        ]);
    }

    private function inspection(Property $property, string $moment): string
    {
        $rooms = ['Sala' => 'Pintura em bom estado, piso sem trincas, duas tomadas funcionando.', 'Cozinha' => 'Pia e torneira sem vazamento, armários com portas alinhadas.', 'Quartos' => 'Paredes limpas, janelas com trincos funcionando.', 'Banheiros' => 'Louças sem trincas, chuveiro e descarga funcionando.'];
        $items = collect($rooms)->map(fn (string $state, string $room): string => "<li><strong>{$room}:</strong> {$state}</li>")->implode('');

        return "<p>Vistoria de {$moment} do imóvel na {$property->street}, {$property->number}, {$property->neighborhood}, {$property->city}/{$property->state}.</p><ul>{$items}</ul><p>As partes conferiram o imóvel e concordam com o estado descrito acima.</p>";
    }

    private function saleDocument(Sale $sale, ContractTemplateKind $kind, SaleDocumentType $type, string $name): void
    {
        $template = $this->template($kind);
        $path = $this->storePdf("sales/{$sale->id}", $template->name, app(RenderSaleDocument::class)->handle($template, $sale));

        SaleDocument::create([
            'account_id' => $sale->account_id,
            'sale_id' => $sale->id,
            'type' => $type,
            'name' => $name,
            'path' => $path,
            'mime_type' => 'application/pdf',
            'size' => Storage::disk(SaleDocument::DISK)->size($path),
        ]);
    }

    /**
     * Keep the service receipt of the expense signed by the provider.
     */
    private function expenseReceipt(Expense $expense): void
    {
        $template = $this->template(ContractTemplateKind::ServiceReceipt);

        $this->expenseDocument($expense, ExpenseDocumentType::SignedReceipt, 'Recibo assinado.pdf', null, app(RenderExpenseDocument::class)->handle($template, $expense));
    }

    /**
     * Keep a document of the expense: a demo photo, when it is on this machine, or a PDF written from the given body.
     */
    private function expenseDocument(Expense $expense, ExpenseDocumentType $type, string $name, ?string $photo, ?string $body = null): void
    {
        if ($photo !== null && ! $this->hasPhoto($photo)) {
            return;
        }

        $directory = "expenses/{$expense->id}";
        $path = $photo !== null
            ? Storage::disk(ExpenseDocument::DISK)->putFile($directory, new File($this->photoPath($photo)))
            : $this->storePdf($directory, $name, (string) $body);

        ExpenseDocument::create([
            'account_id' => $expense->account_id,
            'expense_id' => $expense->id,
            'type' => $type,
            'name' => $name,
            'path' => $path,
            'mime_type' => $photo !== null ? 'image/jpeg' : 'application/pdf',
            'size' => Storage::disk(ExpenseDocument::DISK)->size((string) $path),
        ]);
    }

    /**
     * Lay the body out as a PDF and store it in the directory, returning its path.
     */
    private function storePdf(string $directory, string $title, string $body): string
    {
        $path = $directory.'/'.Str::uuid().'.pdf';

        Storage::disk('local')->put($path, app(BuildDocumentPdf::class)->handle($title, $body)->output());

        return $path;
    }

    private function template(ContractTemplateKind $kind, ?int $accountId = null): ContractTemplate
    {
        return ContractTemplate::withoutGlobalScopes()
            ->where('account_id', $accountId ?? $this->agency->id)
            ->where('kind', $kind)
            ->where('is_default', true)
            ->first()
            ?? app(EnsureDefaultContractTemplate::class)->handle(Account::findOrFail($accountId ?? $this->agency->id), $kind);
    }

    /**
     * Create a property as it was registered on the given date, on the showcase with the given photos.
     *
     * @param  array<string, mixed>  $attributes
     * @param  list<string>  $photos  Demo photos, by name.
     */
    private function property(Account $account, ?Branch $branch, Owner $owner, array $attributes, array $photos = [], ?string $createdAt = null): Property
    {
        $property = $this->at($createdAt ?? $this->day(-15, 1), fn (): Property => Property::create([
            'account_id' => $account->id,
            'branch_id' => $branch?->id,
            'owner_id' => $owner->id,
            'type' => PropertyType::House,
            'for_rent' => true,
            'for_sale' => false,
            'zip_code' => '56200000',
            'neighborhood' => 'Centro',
            'city' => 'Ouricuri',
            'state' => 'PE',
            'status' => PropertyStatus::Available,
            ...$attributes,
        ]));

        if ($photos !== []) {
            $this->publish($property, $photos, $createdAt ?? $this->day(-15, 1));
        }

        return $property;
    }

    /**
     * Add the demo photos found on this machine to the property and put it on the showcase. The photos stay
     * out of the repository, so the showcase goes without them where they are missing.
     *
     * @param  list<string>  $photos
     */
    private function publish(Property $property, array $photos, string $publishedAt): void
    {
        $found = array_values(array_filter($photos, fn (string $photo): bool => $this->hasPhoto($photo)));

        foreach ($found as $order => $photo) {
            $property->photos()->create([
                'account_id' => $property->account_id,
                'path' => Storage::disk(PropertyPhoto::DISK)->putFile("properties/{$property->id}", new File($this->photoPath($photo))),
                'sort_order' => $order,
            ]);
        }

        $property->update(['published_at' => $publishedAt]);
    }

    private function photoPath(string $photo): string
    {
        return database_path("seeders/photos/{$photo}.jpg");
    }

    private function hasPhoto(string $photo): bool
    {
        return is_file($this->photoPath($photo));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function tenant(Account $account, string $name, string $document, array $attributes = []): Tenant
    {
        return Tenant::create([
            'account_id' => $account->id,
            'name' => $name,
            'cpf_cnpj' => $document,
            'email' => str($name)->slug('.').'@email.com',
            'phone' => '879'.substr($document, 0, 8),
            'nationality' => strlen($document) === 11 ? 'brasileira' : null,
            ...$attributes,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function owner(Account $account, string $name, string $document, array $attributes = []): Owner
    {
        return Owner::create([
            'account_id' => $account->id,
            'name' => $name,
            'cpf_cnpj' => $document,
            'email' => str($name)->slug('.').'@email.com',
            'phone' => '879'.substr($document, 0, 8),
            'nationality' => strlen($document) === 11 ? 'brasileira' : null,
            'zip_code' => '56200000',
            'neighborhood' => 'Centro',
            'city' => 'Ouricuri',
            'state' => 'PE',
            ...$attributes,
        ]);
    }

    /**
     * @param  list<Branch>  $branches
     */
    private function user(string $name, string $email, Role $role, array $branches = []): User
    {
        $user = $this->agency->users()->create([
            'name' => $name,
            'email' => $email,
            'password' => 'password',
            'role' => $role,
        ]);

        $user->forceFill(['email_verified_at' => now(), 'last_login_at' => $this->today->subDays(random_int(0, 3))->setTime(8, 30)])->save();

        $user->branches()->attach(array_map(fn (Branch $branch): int => $branch->id, $branches));

        return $user;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function lead(string $name, LeadInterest $interest, LeadSource $source, float $maxPrice, ?User $agent, string $createdAt, array $attributes = []): Lead
    {
        return $this->at($createdAt, fn (): Lead => Lead::create([
            'account_id' => $this->agency->id,
            'branch_id' => $this->matriz->id,
            'user_id' => $agent?->id,
            'name' => $name,
            'phone' => '8799'.sprintf('%07d', crc32($name) % 10000000),
            'email' => str($name)->slug('.').'@email.com',
            'source' => $source,
            'interest' => $interest,
            'property_type' => PropertyType::House,
            'max_price' => $maxPrice,
            'neighborhoods' => 'Centro, Horizonte',
            ...$attributes,
        ]));
    }

    private function deal(Lead $lead, ?Property $property, string $stage, DealType $type, float $value, ?User $agent, string $createdAt): Deal
    {
        return $this->at($createdAt, fn (): Deal => Deal::create([
            'account_id' => $this->agency->id,
            'lead_id' => $lead->id,
            'property_id' => $property?->id,
            'deal_stage_id' => $this->stage($stage)->id,
            'user_id' => $agent?->id,
            'type' => $type,
            'value' => $value,
            'position' => Deal::where('deal_stage_id', $this->stage($stage)->id)->count(),
        ]));
    }

    /**
     * Move the deal to a won or lost stage on the given date.
     */
    private function close(Deal $deal, string $stage, string $closedOn, ?string $lostReason = null): void
    {
        $this->at($closedOn, fn () => app(MoveDeal::class)->handle($deal, $this->stage($stage), null, $lostReason));
    }

    private function stage(string $name): DealStage
    {
        return DealStage::where('account_id', $this->agency->id)->where('name', $name)->firstOrFail();
    }

    private function visit(Deal $deal, string $scheduledAt, VisitStatus $status, User $agent, ?string $feedback = null): void
    {
        $deal->visits()->create([
            'account_id' => $deal->account_id,
            'user_id' => $agent->id,
            'scheduled_at' => $scheduledAt,
            'status' => $status,
            'feedback' => $feedback,
        ]);
    }

    private function proposal(Deal $deal, float $amount, PaymentPlan $plan, string $conditions, ProposalStatus $status, string $proposedOn): void
    {
        $this->at($proposedOn, fn () => $deal->proposals()->create([
            'account_id' => $deal->account_id,
            'amount' => $amount,
            'payment_plan' => $plan,
            'conditions' => $conditions,
            'status' => $status,
            'proposed_on' => $proposedOn,
        ]));
    }

    private function expense(Account $account, Property $property, ExpenseType $type, string $description, float $amount, string $dueDate, ?string $paidOn, ExpensePayer $payer = ExpensePayer::Owner, ?string $providerName = null, ?string $providerDocument = null): Expense
    {
        return $this->at(min($dueDate, $this->today->toDateString()), fn (): Expense => Expense::create([
            'account_id' => $account->id,
            'property_id' => $property->id,
            'type' => $type,
            'description' => $description,
            'provider_name' => $providerName,
            'provider_document' => $providerDocument,
            'charged_to' => $payer,
            'amount' => $amount,
            'due_date' => $dueDate,
            'payment_date' => $paidOn,
            'status' => $paidOn ? ExpenseStatus::Paid : ExpenseStatus::Pending,
        ]));
    }

    /**
     * The first day of the month, counted from this month: -1 is last month.
     */
    private function month(int $offset): CarbonImmutable
    {
        return $this->today->startOfMonth()->addMonthsNoOverflow($offset);
    }

    /**
     * A day of the month counted from this month, kept within the month.
     */
    private function day(int $offset, int $day): string
    {
        $month = $this->month($offset);

        return $month->setDay(min($day, $month->daysInMonth))->toDateString();
    }

    /**
     * Run the callback with the clock set to the given moment, as if it happened then.
     *
     * @template TResult
     *
     * @param  callable(): TResult  $callback
     * @return TResult
     */
    private function at(string $moment, callable $callback): mixed
    {
        $now = CarbonImmutable::parse($moment);
        $now = strlen($moment) <= 10 ? $now->setTime(9, 0) : $now;

        Carbon::setTestNow($now);
        CarbonImmutable::setTestNow($now);

        try {
            return $callback();
        } finally {
            $this->travelBack();
        }
    }

    private function travelBack(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();
    }
}
