<?php

namespace App\Models;

use App\Concerns\BelongsToAccount;
use App\Concerns\BelongsToVisibleBranches;
use App\Enums\MaritalStatus;
use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * @property int $id
 * @property int $account_id
 * @property string $name
 * @property string|null $cpf_cnpj
 * @property string|null $email
 * @property string|null $rg
 * @property string|null $nationality
 * @property MaritalStatus|null $marital_status
 * @property string|null $profession
 * @property string|null $phone
 * @property string|null $zip_code
 * @property string|null $street
 * @property string|null $number
 * @property string|null $complement
 * @property string|null $neighborhood
 * @property string|null $city
 * @property string|null $state
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Account $account
 * @property-read Collection<int, Branch> $branches
 * @property-read Collection<int, Lease> $leases
 * @property-read Collection<int, Payment> $payments
 */
#[Fillable([
    'account_id',
    'name',
    'cpf_cnpj',
    'rg',
    'nationality',
    'marital_status',
    'profession',
    'email',
    'phone',
    'zip_code',
    'street',
    'number',
    'complement',
    'neighborhood',
    'city',
    'state',
])]
class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use BelongsToAccount, BelongsToVisibleBranches, HasFactory, SoftDeletes;

    /**
     * Get the branches the tenant deals with. Tenants linked to none are seen by every branch.
     *
     * @return BelongsToMany<Branch, $this>
     */
    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class);
    }

    /**
     * Link the given tenants to a branch, so the branch lists them.
     *
     * @param  array<int, int>  $tenantIds
     */
    public static function linkToBranch(array $tenantIds, ?int $branchId): void
    {
        if ($branchId === null || $tenantIds === []) {
            return;
        }

        DB::table('branch_tenant')->insertOrIgnore(array_map(
            fn (int $tenantId): array => ['branch_id' => $branchId, 'tenant_id' => $tenantId],
            $tenantIds,
        ));
    }

    /**
     * @return HasMany<Lease, $this>
     */
    public function leases(): HasMany
    {
        return $this->hasMany(Lease::class);
    }

    /**
     * Scope the query to tenants whose name, document, e-mail or phone matches the given term.
     *
     * @param  Builder<Tenant>  $query
     */
    public function scopeSearch(Builder $query, string $term): void
    {
        $query->when($term !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($term) {
            $query->where('name', 'like', "%{$term}%")
                ->orWhere('cpf_cnpj', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")
                ->orWhere('phone', 'like', "%{$term}%");
        }));
    }

    /**
     * Determine whether the tenant has an active lease.
     */
    public function hasActiveLease(): bool
    {
        return $this->leases()->active()->exists();
    }

    /**
     * Determine whether the tenant still owes any payment (rent or extra charge), on any of their leases.
     */
    public function hasOpenPayments(): bool
    {
        return $this->payments()->open()->exists();
    }

    /**
     * @return HasManyThrough<Payment, Lease, $this>
     */
    public function payments(): HasManyThrough
    {
        return $this->hasManyThrough(Payment::class, Lease::class);
    }

    /**
     * Limit the query to the tenants linked to the given branches, plus the ones not linked to
     * any branch yet. Tenants are shared by the whole agency, but each branch only lists the
     * people it deals with.
     *
     * @param  Builder<static>  $query
     * @param  list<int>  $branchIds
     */
    public static function restrictToBranches(Builder $query, array $branchIds): void
    {
        $query->where(fn (Builder $query) => $query
            ->whereDoesntHave('branches')
            ->orWhereHas('branches', fn (Builder $query) => $query->whereIn('branches.id', $branchIds)));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'marital_status' => MaritalStatus::class,
        ];
    }
}
