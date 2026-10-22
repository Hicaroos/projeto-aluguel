<?php

namespace Database\Seeders;

use App\Actions\Deals\EnsureDefaultDealStages;
use App\Actions\Deals\RegisterSale;
use App\Actions\Leases\CreateLease;
use App\Actions\Leases\RenewLease;
use App\Actions\Leases\SyncLeasePayments;
use App\Actions\Payments\CalculateLateCharges;
use App\Actions\Payments\CreateExtraCharge;
use App\Actions\Payments\RegisterReceipt;
use App\Actions\Payouts\CreditGuaranteedRent;
use App\Actions\Payouts\RegisterOwnerPayout;
use App\Actions\Payouts\SummarizeOwnerBalances;
use App\Actions\Properties\SyncPropertyOwners;
use App\Enums\AccountType;
use App\Enums\DealType;
use App\Enums\ExpensePayer;
use App\Enums\ExpenseStatus;
use App\Enums\ExpenseType;
use App\Enums\GuaranteeType;
use App\Enums\LeadInterest;
use App\Enums\LeadSource;
use App\Enums\LeasePurpose;
use App\Enums\PaymentMethod;
use App\Enums\PaymentPlan;
use App\Enums\PaymentStatus;
use App\Enums\PayoutMode;
use App\Enums\PayoutSchedule;
use App\Enums\PropertyStatus;
use App\Enums\PropertyType;
use App\Enums\ProposalStatus;
use App\Enums\Role;
use App\Enums\VisitStatus;
use App\Models\Account;
use App\Models\Branch;
use App\Models\Deal;
use App\Models\DealStage;
use App\Models\Expense;
use App\Models\Lead;
use App\Models\Lease;
use App\Models\Owner;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * Demo data to try the whole system out: a single owner and an agency with branches, a team of
 * every role, co-owned properties, leases with months of history, late payments, guaranteed rent,
 * a renewal, extra charges, expenses, owner payouts, the sales funnel and a sale.
 *
 * Everything goes through the app's own actions, with the clock moved to when it happened, so the
 * payments, the owners' running accounts and the payouts are just as real use would leave them.
 * Every user signs in with the password "password".
 */
class DemoSeeder extends Seeder
{
    /**
     * Seed the demo data.
     */
    public function run(): void
    {
        $this->singleOwner();
        $this->agency();

        $this->travelBack();
    }

    /**
     * A single owner managing their own two houses.
     */
    private function singleOwner(): void
    {
        $account = Account::create(['name' => 'Hicaro Costa', 'type' => AccountType::SingleOwner]);
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

        $house = $this->property($account, null, $owner, ['street' => 'Rua José Bezerra', 'number' => '210', 'rent_amount' => 950]);
        $this->property($account, null, $owner, ['street' => 'Rua do Comércio', 'number' => '45', 'rent_amount' => 1200, 'for_sale' => true, 'sale_price' => 190000]);

        $lease = $this->lease($account, $house, $this->tenant($account, 'Joana Martins', '98765432100'), '2026-04-01', 950, 10);

        $this->receiveMonths($lease, ['2026-04', '2026-05', '2026-06', '2026-07', '2026-08', '2026-09'], lateMonths: ['2026-07' => 6]);

        Expense::create([
            'account_id' => $account->id,
            'property_id' => $house->id,
            'type' => ExpenseType::PropertyTax,
            'description' => 'IPTU 2026 – parcela 9/10',
            'amount' => 62,
            'due_date' => '2026-09-15',
            'payment_date' => '2026-09-14',
            'status' => ExpenseStatus::Paid,
        ]);
    }

    /**
     * An agency with two branches, a team of every role, owners with different payout terms and
     * months of rent, payouts and sales.
     */
    private function agency(): void
    {
        $account = Account::create([
            'name' => 'Imobizilla',
            'legal_name' => 'Imobizilla Negócios Imobiliários Ltda',
            'type' => AccountType::Agency,
            'document' => '12345678000195',
            'creci' => '1234-J',
            'phone' => '8738741234',
            'email' => 'contato@imobizilla.test',
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
        ]);

        $ouricuri = $account->branches()->create(['name' => Branch::MAIN_BRANCH_NAME, 'phone' => '8738741234', 'city' => 'Ouricuri', 'state' => 'PE']);
        $araripina = $account->branches()->create(['name' => 'Araripina', 'phone' => '8738731234', 'city' => 'Araripina', 'state' => 'PE']);

        $this->user($account, 'Ana Administradora', 'admin@imobizilla.test', Role::Admin);
        $this->user($account, 'Gustavo Geral', 'geral@imobizilla.test', Role::General, [$ouricuri, $araripina]);
        $bruno = $this->user($account, 'Bruno Corretor', 'bruno@imobizilla.test', Role::General, [$ouricuri]);
        $this->user($account, 'Renata Corretora', 'renata@imobizilla.test', Role::General, [$araripina]);
        $this->user($account, 'Carla Financeiro', 'carla@imobizilla.test', Role::General, [$ouricuri, $araripina]);

        $antonio = $this->owner($account, 'Antônio Ferreira Lima', '41122233344', ['pix_key' => 'antonio.ferreira@email.com']);
        $maria = $this->owner($account, 'Maria das Graças Silva', '52233344455', ['pix_key' => '87999990010']);
        $joao = $this->owner($account, 'João Silva Neto', '63344455566', ['pix_key' => 'joao.neto@email.com']);
        $construtora = $this->owner($account, 'Construtora Horizonte Ltda', '11222333000181', [
            'pix_key' => '11222333000181',
            'admin_fee_percent' => 8,
            'intermediation_fee_percent' => 50,
            'payout_schedule' => PayoutSchedule::MonthlyDay,
            'payout_day' => 10,
        ]);
        $lucia = $this->owner($account, 'Lúcia Alves Pereira', '74455566677', [
            'pix_key' => 'lucia.alves@email.com',
            'payout_mode' => PayoutMode::Guaranteed,
        ]);
        $pedro = $this->owner($account, 'Pedro Santos Rocha', '85566677788', ['pix_key' => '87999990020']);

        // Antônio: an apartment rented since March (with the intermediation fee on the first rent),
        // a house rented in August with a deposit, and a house still free to rent.
        $apartment = $this->property($account, $ouricuri, $antonio, ['type' => PropertyType::Apartment, 'street' => 'Rua Siqueira Campos', 'number' => '77', 'complement' => 'Apto 102', 'rent_amount' => 1100]);
        $apartmentLease = $this->lease($account, $apartment, $this->tenant($account, 'Fernanda Costa', '11122233396'), '2026-03-01', 1100, 10);
        $this->receiveMonths($apartmentLease, ['2026-03', '2026-04', '2026-05', '2026-06', '2026-07', '2026-08', '2026-09'], lateMonths: ['2026-06' => 12]);

        $repair = app(CreateExtraCharge::class)->handle($apartmentLease, ['description' => 'Conserto da pia', 'amount' => 180, 'due_date' => '2026-09-20']);
        $this->receive($repair, '2026-09-22');
        $copy = app(CreateExtraCharge::class)->handle($apartmentLease, ['description' => 'Segunda via de boleto', 'amount' => 15, 'due_date' => '2026-09-20', 'pass_to_owner' => false]);
        $this->receive($copy, '2026-09-22');

        $house = $this->property($account, $ouricuri, $antonio, ['street' => 'Rua Joaquim Nabuco', 'number' => '210', 'rent_amount' => 1300]);
        $houseLease = $this->lease($account, $house, $this->tenant($account, 'Ricardo Gomes', '22233344405'), '2026-08-01', 1300, 5, [
            'guarantee_type' => GuaranteeType::Deposit,
            'deposit_amount' => 3900,
        ]);
        $this->receiveMonths($houseLease, ['2026-08', '2026-09', '2026-10']);

        $this->property($account, $ouricuri, $antonio, ['street' => 'Rua Pernambuco', 'number' => '15', 'rent_amount' => 900]);

        $this->expense($account, $apartment, ExpenseType::PropertyTax, 'IPTU 2026 – parcela 9/10', 85, '2026-09-15', '2026-09-15');
        $this->expense($account, $house, ExpenseType::Maintenance, 'Vistoria de entrada', 150, '2026-08-02', '2026-08-02', ExpensePayer::Agency);

        // Maria (60%) and João (40%) inherited a house together.
        $inherited = $this->property($account, $ouricuri, $maria, ['street' => 'Rua São José', 'number' => '150', 'rent_amount' => 2000, 'bedrooms' => 3]);
        app(SyncPropertyOwners::class)->handle($inherited, [$joao->id => 40.0]);
        $inheritedLease = $this->lease($account, $inherited, $this->tenant($account, 'Marcos Vieira', '33344455514'), '2026-05-01', 2000, 10);
        $this->receiveMonths($inheritedLease, ['2026-05', '2026-06', '2026-07', '2026-08', '2026-09', '2026-10'], lateMonths: ['2026-08' => 4]);
        $this->expense($account, $inherited, ExpenseType::Maintenance, 'Conserto do telhado', 600, '2026-10-03', '2026-10-03', providerName: 'José Telhados', providerDocument: '55566677788');

        // The building company: 8% admin fee and payouts on the 10th; an office rented, a lot sold
        // with the down payment received by the agency, and a house for sale in the funnel.
        $office = $this->property($account, $ouricuri, $construtora, ['type' => PropertyType::Office, 'street' => 'Avenida Brasil', 'number' => '500', 'complement' => 'Sala 3', 'rent_amount' => 1800]);
        $officeLease = $this->lease($account, $office, $this->tenant($account, 'Contabilidade Exata Ltda', '44555666000172'), '2026-06-01', 1800, 5, ['purpose' => LeasePurpose::Commercial]);
        $this->receiveMonths($officeLease, ['2026-06', '2026-07', '2026-08', '2026-09', '2026-10']);
        $this->expense($account, $office, ExpenseType::CondoFee, 'Condomínio de outubro', 450, '2026-10-20', null);

        $lot = $this->property($account, $ouricuri, $construtora, ['type' => PropertyType::Land, 'street' => 'Rua Projetada 4', 'number' => 'Lote 12', 'for_rent' => false, 'rent_amount' => null, 'for_sale' => true, 'sale_price' => 95000, 'land_area' => 300, 'land_front' => 10, 'land_depth' => 30]);
        $forSale = $this->property($account, $ouricuri, $construtora, ['street' => 'Rua Horizonte', 'number' => '27', 'for_rent' => false, 'rent_amount' => null, 'for_sale' => true, 'sale_price' => 295000, 'bedrooms' => 3, 'suites' => 1, 'built_area' => 112, 'land_area' => 200]);

        // Lúcia: guaranteed rent. The tenant paid a part of September and nothing of October yet.
        $guaranteed = $this->property($account, $araripina, $lucia, ['street' => 'Rua Coronel Modesto', 'number' => '300', 'city' => 'Araripina', 'rent_amount' => 1500]);
        $guaranteedLease = $this->lease($account, $guaranteed, $this->tenant($account, 'Paulo Henrique Dias', '55566677723'), '2026-07-01', 1500, 10);
        $this->receiveMonths($guaranteedLease, ['2026-07', '2026-08']);
        $this->receive($this->rentOf($guaranteedLease, '2026-09'), '2026-09-25', amount: 500);

        // Pedro: a lease renewed in September, with a 12% admin fee and late charges split in half.
        $renewed = $this->property($account, $araripina, $pedro, ['type' => PropertyType::Apartment, 'street' => 'Rua Sete de Setembro', 'number' => '55', 'city' => 'Araripina', 'rent_amount' => 1200]);
        $renewedLease = $this->lease($account, $renewed, $this->tenant($account, 'Camila Ribeiro', '66677788832'), '2025-10-01', 1200, 10, [
            'end_date' => '2026-09-30',
            'admin_fee_percent' => 12,
            'late_charges_owner_percent' => 50,
        ]);

        $this->at('2026-09-20', fn () => app(RenewLease::class)->handle($renewedLease, CarbonImmutable::parse('2027-09-30'), 'Renovado por mais 12 meses.'));
        $this->receiveMonths($renewedLease, [
            '2025-10', '2025-11', '2025-12', '2026-01', '2026-02', '2026-03', '2026-04',
            '2026-05', '2026-06', '2026-07', '2026-08', '2026-09', '2026-10',
        ], lateMonths: ['2026-02' => 8, '2026-09' => 3]);

        foreach (Payment::withoutGlobalScopes()->where('account_id', $account->id)->rent()->get() as $payment) {
            app(CreditGuaranteedRent::class)->handle($payment);
        }

        // Monthly payouts up to September; October is still to be paid out.
        foreach (['2026-04-16', '2026-05-16', '2026-06-16', '2026-07-16', '2026-08-16', '2026-09-16', '2026-10-01'] as $paidOn) {
            foreach ([$antonio, $maria, $joao, $construtora, $lucia, $pedro] as $owner) {
                $this->payOut($owner, $paidOn);
            }
        }

        $this->salesFunnel($account, $bruno, $lot, $forSale, Property::withoutGlobalScopes()->where('street', 'Rua Pernambuco')->firstOrFail());
    }

    /**
     * Leads, deals at every stage, a visit, a proposal and a sale whose down payment the agency
     * received for the seller.
     */
    private function salesFunnel(Account $account, User $agent, Property $lot, Property $forSale, Property $forRent): void
    {
        $stages = app(EnsureDefaultDealStages::class)->handle($account)->keyBy('name');
        $stage = fn (string $name): int => $stages->get($name)->id ?? DealStage::where('account_id', $account->id)->value('id');

        $carlos = $this->lead($account, $agent, 'Carlos Comprador', LeadInterest::Buy, LeadSource::Instagram, 320000);
        $visitDeal = Deal::create(['account_id' => $account->id, 'lead_id' => $carlos->id, 'property_id' => $forSale->id, 'deal_stage_id' => $stage('Visita'), 'user_id' => $agent->id, 'type' => DealType::Sale, 'value' => 295000, 'position' => 0]);
        $visitDeal->visits()->create(['account_id' => $account->id, 'user_id' => $agent->id, 'scheduled_at' => '2026-10-14 10:00:00', 'status' => VisitStatus::Scheduled]);

        $fernanda = $this->lead($account, $agent, 'Fernanda Lima', LeadInterest::Buy, LeadSource::Portal, 290000);
        $proposalDeal = Deal::create(['account_id' => $account->id, 'lead_id' => $fernanda->id, 'property_id' => $forSale->id, 'deal_stage_id' => $stage('Proposta'), 'user_id' => $agent->id, 'type' => DealType::Sale, 'value' => 280000, 'position' => 0]);
        $proposalDeal->visits()->create(['account_id' => $account->id, 'user_id' => $agent->id, 'scheduled_at' => '2026-10-04 15:00:00', 'status' => VisitStatus::Done, 'feedback' => 'Gostou muito da cozinha.']);
        $proposalDeal->proposals()->create(['account_id' => $account->id, 'amount' => 280000, 'payment_plan' => PaymentPlan::Financing, 'conditions' => 'Financiamento pela Caixa, entrada de 20%.', 'status' => ProposalStatus::Pending, 'proposed_on' => '2026-10-08']);

        $roberto = $this->lead($account, $agent, 'Roberto Alves', LeadInterest::Rent, LeadSource::WhatsApp, 1000);
        Deal::create(['account_id' => $account->id, 'lead_id' => $roberto->id, 'property_id' => $forRent->id, 'deal_stage_id' => $stage('Novo contato'), 'user_id' => $agent->id, 'type' => DealType::Rent, 'value' => 900, 'position' => 0]);

        $this->lead($account, null, 'Sandra Moura', LeadInterest::Buy, LeadSource::Referral, 150000);

        $this->at('2026-10-02', fn () => app(RegisterSale::class)->handle($lot, [
            'buyer_name' => 'Juliana Rocha',
            'buyer_document' => '77788899940',
            'buyer_phone' => '87999990030',
            'buyer_email' => 'juliana.rocha@email.com',
            'price' => 95000,
            'sold_on' => '2026-10-02',
            'payment_plan' => PaymentPlan::Cash,
            'down_payment' => 10000,
            'down_payment_received_by_agency' => true,
            'deed_deadline' => '2026-11-30',
            'commission_percent' => 6,
            'user_id' => $agent->id,
            'notes' => 'Sinal pago por Pix na imobiliária; escritura em novembro.',
        ]));
    }

    /**
     * Pay the owner what is theirs to receive on the given date, if anything.
     */
    private function payOut(Owner $owner, string $paidOn): void
    {
        $this->at($paidOn, function () use ($owner, $paidOn): void {
            $available = app(SummarizeOwnerBalances::class)->balances([$owner->id])[$owner->id]['available'] ?? 0.0;

            if ($available <= 0) {
                return;
            }

            app(RegisterOwnerPayout::class)->handle(
                $owner,
                ['amount' => $available, 'paid_on' => $paidOn, 'payment_method' => PaymentMethod::Pix->value],
                User::withoutGlobalScopes()->where('email', 'carla@imobizilla.test')->firstOrFail(),
            );
        });
    }

    /**
     * Receive the rent of the given months, paid on the due date or the given days late.
     *
     * @param  list<string>  $months  As Y-m.
     * @param  array<string, int>  $lateMonths  Days late, keyed by month.
     */
    private function receiveMonths(Lease $lease, array $months, array $lateMonths = []): void
    {
        app(SyncLeasePayments::class)->handle($lease->refresh());

        foreach ($months as $month) {
            $payment = $this->rentOf($lease, $month);
            $paidOn = $payment->due_date->copy()->addDays($lateMonths[$month] ?? 0)->toDateString();

            $this->receive($payment, $paidOn);
        }
    }

    /**
     * Receive a payment on the given date, with the late charges the lease sets when late.
     */
    private function receive(Payment $payment, string $paidOn, ?float $amount = null): void
    {
        $amount ??= (float) $payment->amount;
        $charges = app(CalculateLateCharges::class)->handle($payment->load('lease'), $amount, CarbonImmutable::parse($paidOn));

        app(RegisterReceipt::class)->handle($payment, [
            'amount' => $amount,
            'late_fee_amount' => $charges['late_fee'],
            'interest_amount' => $charges['interest'],
            'date' => $paidOn,
            'payment_method' => PaymentMethod::Pix,
        ]);
    }

    /**
     * Get the rent payment of the lease for the month.
     */
    private function rentOf(Lease $lease, string $month): Payment
    {
        return Payment::withoutGlobalScopes()
            ->where('lease_id', $lease->id)
            ->rent()
            ->whereDate('reference_month', "{$month}-01")
            ->where('status', '!=', PaymentStatus::Canceled)
            ->firstOrFail();
    }

    /**
     * Create an active lease as it was signed on its start date.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function lease(Account $account, Property $property, Tenant $tenant, string $startDate, float $amount, int $dueDay, array $attributes = []): Lease
    {
        return $this->at($startDate, fn (): Lease => app(CreateLease::class)->handle($account->id, [
            'property_id' => $property->id,
            'tenant_id' => $tenant->id,
            'start_date' => $startDate,
            'end_date' => CarbonImmutable::parse($startDate)->addMonths(30)->subDay()->toDateString(),
            'amount' => $amount,
            'due_day' => $dueDay,
            'guarantee_type' => GuaranteeType::None,
            ...$attributes,
        ]));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function property(Account $account, ?Branch $branch, Owner $owner, array $attributes): Property
    {
        return Property::create([
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
        ]);
    }

    private function tenant(Account $account, string $name, string $document): Tenant
    {
        return Tenant::create([
            'account_id' => $account->id,
            'name' => $name,
            'cpf_cnpj' => $document,
            'email' => str($name)->slug('.').'@email.com',
            'phone' => '879'.substr($document, 0, 8),
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
            ...$attributes,
        ]);
    }

    /**
     * @param  list<Branch>  $branches
     */
    private function user(Account $account, string $name, string $email, Role $role, array $branches = []): User
    {
        $user = $account->users()->create([
            'name' => $name,
            'email' => $email,
            'password' => 'password',
            'role' => $role,
        ]);

        $user->forceFill(['email_verified_at' => now()])->save();

        $user->branches()->attach(array_map(fn (Branch $branch): int => $branch->id, $branches));

        return $user;
    }

    private function lead(Account $account, ?User $agent, string $name, LeadInterest $interest, LeadSource $source, float $maxPrice): Lead
    {
        return Lead::create([
            'account_id' => $account->id,
            'branch_id' => Branch::where('account_id', $account->id)->where('name', Branch::MAIN_BRANCH_NAME)->value('id'),
            'user_id' => $agent?->id,
            'name' => $name,
            'phone' => '8799'.random_int(1000000, 9999999),
            'email' => str($name)->slug('.').'@email.com',
            'source' => $source,
            'interest' => $interest,
            'property_type' => PropertyType::House,
            'max_price' => $maxPrice,
            'neighborhoods' => 'Centro, Horizonte',
        ]);
    }

    private function expense(Account $account, Property $property, ExpenseType $type, string $description, float $amount, string $dueDate, ?string $paidOn, ExpensePayer $payer = ExpensePayer::Owner, ?string $providerName = null, ?string $providerDocument = null): void
    {
        Expense::create([
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
        ]);
    }

    /**
     * Run the callback with the clock set to the given date, as if it happened then.
     *
     * @template TResult
     *
     * @param  callable(): TResult  $callback
     * @return TResult
     */
    private function at(string $date, callable $callback): mixed
    {
        $now = CarbonImmutable::parse($date)->setTime(9, 0);

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
