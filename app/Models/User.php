<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property int|null $account_id
 * @property Role $role
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Account|null $account
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    /**
     * The session key holding the branch picked in the branch selector.
     */
    public const string SELECTED_BRANCH_SESSION_KEY = 'selected_branch_id';

    /**
     * @var array<string, string>
     */
    protected $attributes = [
        'role' => 'admin',
    ];

    /**
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Get the branches the user may work with. Null means every branch of the account.
     *
     * @return list<int>|null
     */
    public function accessibleBranchIds(): ?array
    {
        return null;
    }

    /**
     * Get the branch picked in the branch selector, as long as the user may still access it.
     */
    public function selectedBranchId(): ?int
    {
        if (! $this->account?->isAgency()) {
            return null;
        }

        $selected = session()->get(self::SELECTED_BRANCH_SESSION_KEY);

        if (! is_int($selected)) {
            return null;
        }

        $accessible = $this->accessibleBranchIds();

        $isAccessible = $accessible === null
            ? Branch::withoutGlobalScope(Branch::SCOPE)->where('account_id', $this->account_id)->whereKey($selected)->exists()
            : in_array($selected, $accessible, true);

        return $isAccessible ? $selected : null;
    }

    /**
     * Get the branches whose records the user is looking at: the selected one, or every branch
     * they may access. Null means no restriction, as for single owner accounts.
     *
     * @return list<int>|null
     */
    public function visibleBranchIds(): ?array
    {
        if (! $this->account?->isAgency()) {
            return null;
        }

        $selected = $this->selectedBranchId();

        return $selected !== null ? [$selected] : $this->accessibleBranchIds();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'role' => Role::class,
        ];
    }
}
