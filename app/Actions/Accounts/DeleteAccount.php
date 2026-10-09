<?php

namespace App\Actions\Accounts;

use App\Models\Account;
use App\Models\LeaseDocument;
use App\Models\PropertyPhoto;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DeleteAccount
{
    /**
     * Tables holding the account's records, children first so foreign keys never block the deletion.
     *
     * @var list<string>
     */
    private const array ACCOUNT_TABLES = [
        'receipts',
        'payments',
        'lease_documents',
        'leases',
        'property_photos',
        'expenses',
        'properties',
        'branches',
        'tenants',
        'contract_templates',
        'owners',
        'users',
    ];

    /**
     * Tables whose records belong to the account through its leases.
     *
     * @var list<string>
     */
    private const array LEASE_TABLES = ['guarantors', 'lease_adjustments', 'lease_renewals'];

    /**
     * Remove the user leaving the account. When they are its only user, the whole account
     * goes with them: every record and every stored file, so nothing is left behind.
     */
    public function handle(User $user): void
    {
        $account = $user->account;

        if ($account === null || $account->users()->whereKeyNot($user->id)->exists()) {
            $user->delete();

            return;
        }

        $this->deleteEverything($account);
    }

    private function deleteEverything(Account $account): void
    {
        $propertyIds = DB::table('properties')->where('account_id', $account->id)->pluck('id');
        $leaseIds = DB::table('leases')->where('account_id', $account->id)->pluck('id');

        DB::transaction(function () use ($account, $leaseIds): void {
            DB::table('branch_tenant')
                ->whereIn('branch_id', DB::table('branches')->where('account_id', $account->id)->select('id'))
                ->delete();

            foreach (self::LEASE_TABLES as $table) {
                DB::table($table)->whereIn('lease_id', $leaseIds)->delete();
            }

            foreach (self::ACCOUNT_TABLES as $table) {
                DB::table($table)->where('account_id', $account->id)->delete();
            }

            $account->delete();
        });

        $propertyIds->each(fn (int $id) => Storage::disk(PropertyPhoto::DISK)->deleteDirectory("properties/{$id}"));
        $leaseIds->each(fn (int $id) => Storage::disk(LeaseDocument::DISK)->deleteDirectory("leases/{$id}"));
        Storage::disk(Account::LOGO_DISK)->deleteDirectory("accounts/{$account->id}");
    }
}
