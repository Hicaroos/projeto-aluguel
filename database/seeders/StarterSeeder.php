<?php

namespace Database\Seeders;

use App\Enums\AccountType;
use App\Enums\PayoutMode;
use App\Enums\PayoutSchedule;
use App\Enums\PropertyStatus;
use App\Enums\PropertyType;
use App\Enums\Role;
use App\Models\Account;
use App\Models\Branch;
use App\Models\Owner;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * A clean start to try the system from scratch: an agency with its team, three owners with five
 * houses each (some of them for sale) and ten tenants, with no leases, payments or sales yet.
 * Every user signs in with the password "password".
 */
class StarterSeeder extends Seeder
{
    /**
     * Seed the starter data.
     */
    public function run(): void
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
            'renewal_fee_percent' => 0,
            'admin_fee_with_intermediation' => false,
            'admin_fee_on_extra_charges' => false,
            'extra_charges_to_owner' => true,
            'late_charges_owner_percent' => 100,
            'payout_mode' => PayoutMode::Received,
            'payout_schedule' => PayoutSchedule::DaysAfter,
            'payout_days' => 5,
            'payout_day' => 10,
        ]);

        $branch = $account->branches()->create(['name' => Branch::MAIN_BRANCH_NAME, 'phone' => '8738741234', 'city' => 'Ouricuri', 'state' => 'PE']);

        $this->user($account, 'Ana Administradora', 'admin@imobizilla.test', Role::Admin);
        $this->user($account, 'Gustavo Geral', 'geral@imobizilla.test', Role::General, $branch);
        $this->user($account, 'Bruno Corretor', 'bruno@imobizilla.test', Role::Agent, $branch);
        $this->user($account, 'Carla Financeiro', 'carla@imobizilla.test', Role::Finance, $branch);

        $owners = [
            [
                $this->owner($account, 'Antônio Ferreira Lima', '41122233344', 'antonio.ferreira@email.com'),
                [
                    ['street' => 'Rua Joaquim Nabuco', 'number' => '210', 'neighborhood' => 'Centro', 'rent_amount' => 1300, 'bedrooms' => 3, 'bathrooms' => 2, 'parking_spaces' => 1, 'built_area' => 110, 'land_area' => 200],
                    ['street' => 'Rua Pernambuco', 'number' => '15', 'neighborhood' => 'Centro', 'rent_amount' => 900, 'bedrooms' => 2, 'bathrooms' => 1, 'parking_spaces' => 1, 'built_area' => 70, 'land_area' => 150],
                    ['street' => 'Rua São Sebastião', 'number' => '88', 'neighborhood' => 'São Sebastião', 'rent_amount' => 1100, 'bedrooms' => 3, 'bathrooms' => 1, 'parking_spaces' => 1, 'built_area' => 95, 'land_area' => 180],
                    ['street' => 'Rua Nova', 'number' => '402', 'neighborhood' => 'Planalto', 'rent_amount' => 850, 'bedrooms' => 2, 'bathrooms' => 1, 'parking_spaces' => 0, 'built_area' => 65, 'land_area' => 120],
                    ['street' => 'Avenida Frei Damião', 'number' => '1200', 'neighborhood' => 'Horizonte', 'for_rent' => false, 'for_sale' => true, 'sale_price' => 280000, 'bedrooms' => 3, 'bathrooms' => 2, 'parking_spaces' => 2, 'built_area' => 140, 'land_area' => 300],
                ],
            ],
            [
                $this->owner($account, 'Maria das Graças Silva', '52233344455', '87999990010'),
                [
                    ['street' => 'Rua São José', 'number' => '150', 'neighborhood' => 'Centro', 'rent_amount' => 2000, 'bedrooms' => 4, 'bathrooms' => 3, 'parking_spaces' => 2, 'built_area' => 180, 'land_area' => 360],
                    ['street' => 'Rua do Rosário', 'number' => '33', 'neighborhood' => 'Centro', 'rent_amount' => 1200, 'bedrooms' => 3, 'bathrooms' => 2, 'parking_spaces' => 1, 'built_area' => 100, 'land_area' => 200],
                    ['street' => 'Rua Cícero Romão', 'number' => '77', 'neighborhood' => 'Alto da Boa Vista', 'rent_amount' => 950, 'bedrooms' => 2, 'bathrooms' => 1, 'parking_spaces' => 1, 'built_area' => 75, 'land_area' => 160],
                    ['street' => 'Rua Projetada A', 'number' => '12', 'neighborhood' => 'Horizonte', 'for_rent' => false, 'for_sale' => true, 'sale_price' => 210000, 'bedrooms' => 3, 'bathrooms' => 2, 'parking_spaces' => 2, 'built_area' => 120, 'land_area' => 250],
                    ['street' => 'Rua Padre Cícero', 'number' => '501', 'neighborhood' => 'São Sebastião', 'rent_amount' => 1500, 'for_sale' => true, 'sale_price' => 320000, 'bedrooms' => 3, 'bathrooms' => 3, 'parking_spaces' => 2, 'built_area' => 160, 'land_area' => 300],
                ],
            ],
            [
                $this->owner($account, 'Pedro Santos Rocha', '85566677788', 'pedro.rocha@email.com'),
                [
                    ['street' => 'Rua Coronel Modesto', 'number' => '300', 'neighborhood' => 'Centro', 'rent_amount' => 1500, 'bedrooms' => 3, 'bathrooms' => 2, 'parking_spaces' => 2, 'built_area' => 130, 'land_area' => 250],
                    ['street' => 'Rua Sete de Setembro', 'number' => '55', 'neighborhood' => 'Centro', 'rent_amount' => 1000, 'bedrooms' => 2, 'bathrooms' => 1, 'parking_spaces' => 1, 'built_area' => 80, 'land_area' => 150],
                    ['street' => 'Rua Bom Jesus', 'number' => '19', 'neighborhood' => 'Planalto', 'rent_amount' => 800, 'bedrooms' => 2, 'bathrooms' => 1, 'parking_spaces' => 0, 'built_area' => 60, 'land_area' => 120],
                    ['street' => 'Rua das Flores', 'number' => '240', 'neighborhood' => 'Alto da Boa Vista', 'rent_amount' => 1150, 'bedrooms' => 3, 'bathrooms' => 2, 'parking_spaces' => 1, 'built_area' => 105, 'land_area' => 200],
                    ['street' => 'Rua Horizonte', 'number' => '27', 'neighborhood' => 'Horizonte', 'for_rent' => false, 'for_sale' => true, 'sale_price' => 295000, 'bedrooms' => 3, 'bathrooms' => 2, 'parking_spaces' => 2, 'built_area' => 150, 'land_area' => 300],
                ],
            ],
        ];

        foreach ($owners as [$owner, $houses]) {
            foreach ($houses as $house) {
                $this->house($account, $branch, $owner, $house);
            }
        }

        foreach ([
            ['Fernanda Costa', '11122233396'],
            ['Ricardo Gomes', '22233344405'],
            ['Marcos Vieira', '33344455514'],
            ['Paulo Henrique Dias', '44455566623'],
            ['Juliana Rocha', '55566677732'],
            ['Camila Ribeiro', '66677788841'],
            ['Thiago Almeida', '77788899950'],
            ['Larissa Nunes', '88899900069'],
            ['Eduardo Pinheiro', '99900011178'],
            ['Beatriz Carvalho', '10120230387'],
        ] as [$name, $document]) {
            Tenant::create([
                'account_id' => $account->id,
                'name' => $name,
                'cpf_cnpj' => $document,
                'email' => str($name)->slug('.').'@email.com',
                'phone' => '879'.substr($document, 0, 8),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function house(Account $account, Branch $branch, Owner $owner, array $attributes): Property
    {
        return Property::create([
            'account_id' => $account->id,
            'branch_id' => $branch->id,
            'owner_id' => $owner->id,
            'type' => PropertyType::House,
            'for_rent' => true,
            'for_sale' => false,
            'zip_code' => '56200000',
            'city' => 'Ouricuri',
            'state' => 'PE',
            'status' => PropertyStatus::Available,
            ...$attributes,
        ]);
    }

    private function owner(Account $account, string $name, string $document, string $pixKey): Owner
    {
        return Owner::create([
            'account_id' => $account->id,
            'name' => $name,
            'cpf_cnpj' => $document,
            'email' => str($name)->slug('.').'@email.com',
            'phone' => '879'.substr($document, 0, 8),
            'pix_key' => $pixKey,
        ]);
    }

    private function user(Account $account, string $name, string $email, Role $role, ?Branch $branch = null): User
    {
        $user = $account->users()->create([
            'name' => $name,
            'email' => $email,
            'password' => 'password',
            'role' => $role,
        ]);

        $user->forceFill(['email_verified_at' => now()])->save();

        if ($branch !== null) {
            $user->branches()->attach($branch);
        }

        return $user;
    }
}
