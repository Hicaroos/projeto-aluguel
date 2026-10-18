<?php

namespace App\Concerns;

use App\Models\Account;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Keep each account's records to itself: while a user is signed in, every query only
 * sees the records of their account, and new records join it by default. A signed in
 * user without an account sees no records at all.
 *
 * With no one signed in (scheduled commands, sign up, scripts, the public showcase)
 * nothing is filtered, so that work can still reach every account. Skip the filter on
 * purpose with `withoutGlobalScope(Model::SCOPE)`, e.g. `Lease::withoutGlobalScope(Lease::SCOPE)`.
 *
 * @mixin Model
 */
trait BelongsToAccount
{
    public const string SCOPE = 'account';

    public static function bootBelongsToAccount(): void
    {
        static::addGlobalScope(self::SCOPE, function (Builder $query): void {
            if (! Auth::check()) {
                return;
            }

            $accountId = self::signedInAccountId();

            $accountId === null
                ? $query->whereRaw('1 = 0')
                : $query->where($query->qualifyColumn('account_id'), $accountId);
        });

        static::creating(function (Model $model): void {
            if ($model->getAttribute('account_id') === null) {
                $model->setAttribute('account_id', self::signedInAccountId());
            }
        });
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Get the account of the signed in user, if any.
     */
    private static function signedInAccountId(): ?int
    {
        return Auth::user()?->account_id;
    }
}
