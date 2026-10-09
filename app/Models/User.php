<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\Permission;
use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
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
 * @property string|null $invitation_token Hash of the token in the invitation link, while the invitation is pending.
 * @property Carbon|null $invited_at
 * @property Carbon|null $deactivated_at
 * @property Carbon|null $last_login_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Account|null $account
 * @property-read Collection<int, Branch> $branches
 */
#[Fillable(['name', 'email', 'password', 'role', 'invitation_token', 'invited_at', 'deactivated_at', 'last_login_at'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token', 'invitation_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    /**
     * The session key holding the branch picked in the branch selector.
     */
    public const string SELECTED_BRANCH_SESSION_KEY = 'selected_branch_id';

    /**
     * How long an invitation link stays valid.
     */
    public const int INVITATION_LIFETIME_DAYS = 7;

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
     * Get the branches the user works in. Administrators work in every branch and have none.
     *
     * @return BelongsToMany<Branch, $this>
     */
    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class);
    }

    /**
     * Scope the query to the users of the given invitation link, while it is still valid.
     *
     * @param  Builder<User>  $query
     */
    public function scopeWithValidInvitation(Builder $query, string $token): void
    {
        $query->where('invitation_token', hash('sha256', $token))
            ->where('invited_at', '>=', now()->subDays(self::INVITATION_LIFETIME_DAYS));
    }

    /**
     * Start a new invitation for the user, replacing any previous link, and get the token for the link.
     */
    public function startInvitation(): string
    {
        $token = Str::random(48);

        $this->update([
            'invitation_token' => hash('sha256', $token),
            'invited_at' => now(),
        ]);

        return $token;
    }

    /**
     * Determine whether the user was invited and has not created their password yet.
     */
    public function hasPendingInvitation(): bool
    {
        return $this->invitation_token !== null;
    }

    /**
     * Determine whether the user may still use the app.
     */
    public function isActive(): bool
    {
        return $this->deactivated_at === null;
    }

    /**
     * Determine whether the user created the account. They always stay an active administrator,
     * so the account is never left without one.
     */
    public function isAccountOwner(): bool
    {
        return $this->account_id !== null
            && (int) static::where('account_id', $this->account_id)->min('id') === $this->id;
    }

    /**
     * Determine whether the user may do what the permission allows.
     */
    public function hasPermission(Permission $permission): bool
    {
        if ($permission === Permission::ManageAgency && ! ($this->account?->isAgency() ?? false)) {
            return false;
        }

        return in_array($permission, $this->role->permissions(), true);
    }

    /**
     * Get the branches the user may work with. Null means every branch of the account.
     *
     * @return array<int, int>|null
     */
    public function accessibleBranchIds(): ?array
    {
        if ($this->role->seesEveryBranch()) {
            return null;
        }

        return $this->branches()
            ->get(['branches.id'])
            ->map(fn (Branch $branch): int => $branch->id)
            ->all();
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
     * @return array<int, int>|null
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
            'invited_at' => 'datetime',
            'deactivated_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }
}
