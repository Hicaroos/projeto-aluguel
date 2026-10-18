<?php

namespace App\Actions\Fortify;

use Illuminate\Http\Request;
use Laravel\Fortify\LoginRateLimiter as FortifyLoginRateLimiter;

/**
 * Fortify locks an e-mail out for a minute after five failed logins from the same address. On top
 * of that, cap the failed logins of each address whatever the e-mails tried, so nobody can try a
 * common password on many accounts. Signing in doesn't lift this cap.
 */
class LoginRateLimiter extends FortifyLoginRateLimiter
{
    /**
     * How many failed logins one address may make within the window.
     */
    public const int MAX_FAILURES_PER_ADDRESS = 30;

    /**
     * How long the cap of an address lasts, in seconds.
     */
    private const int ADDRESS_WINDOW_SECONDS = 600;

    /**
     * Determine whether the e-mail or the address has failed too many times.
     */
    public function tooManyAttempts(Request $request): bool
    {
        return parent::tooManyAttempts($request) || $this->addressIsLockedOut($request);
    }

    /**
     * Count a failed login for the e-mail and for the address.
     */
    public function increment(Request $request): void
    {
        parent::increment($request);

        $this->limiter->hit($this->addressKey($request), self::ADDRESS_WINDOW_SECONDS);
    }

    /**
     * Get the seconds until the login may be tried again.
     */
    public function availableIn(Request $request): int
    {
        return $this->addressIsLockedOut($request)
            ? $this->limiter->availableIn($this->addressKey($request))
            : parent::availableIn($request);
    }

    private function addressIsLockedOut(Request $request): bool
    {
        return $this->limiter->tooManyAttempts($this->addressKey($request), self::MAX_FAILURES_PER_ADDRESS);
    }

    private function addressKey(Request $request): string
    {
        return 'login-address|'.$request->ip();
    }
}
