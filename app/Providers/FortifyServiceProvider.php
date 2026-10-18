<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\LoginRateLimiter;
use App\Actions\Fortify\ResetUserPassword;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Auth\PasswordBroker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\Http\Responses\SuccessfulPasswordResetLinkRequestResponse;
use Laravel\Fortify\LoginRateLimiter as FortifyLoginRateLimiter;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(FortifyLoginRateLimiter::class, LoginRateLimiter::class);

        // Answer a reset request for an e-mail without an account as one for an e-mail with an account,
        // so the form doesn't tell who uses the app.
        $this->app->bind(
            FailedPasswordResetLinkRequestResponse::class,
            fn (): SuccessfulPasswordResetLinkRequestResponse => new SuccessfulPasswordResetLinkRequestResponse(PasswordBroker::RESET_LINK_SENT),
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureActions();
        $this->configureViews();
        $this->configureRateLimiting();
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::createUsersUsing(CreateNewUser::class);

        Fortify::authenticateUsing(function (Request $request): ?User {
            $user = User::where('email', $request->string(Fortify::username())->toString())->first();
            $password = $request->string('password')->toString();

            if ($user === null) {
                // Hash anyway, so the answer takes as long as for a wrong password and doesn't tell
                // which e-mails have an account.
                Hash::make($password);

                return null;
            }

            if (! Hash::check($password, $user->password)) {
                return null;
            }

            // Until they accept the invitation, only its link lets an invited member in.
            if ($user->hasPendingInvitation()) {
                throw ValidationException::withMessages([
                    Fortify::username() => __('Seu convite ainda não foi aceito. Peça um novo link ao administrador da imobiliária.'),
                ]);
            }

            if (! $user->isActive()) {
                throw ValidationException::withMessages([
                    Fortify::username() => __('Seu acesso foi desativado. Fale com o administrador da imobiliária.'),
                ]);
            }

            return $user;
        });
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(fn (Request $request) => Inertia::render('auth/Login', [
            'canResetPassword' => Features::enabled(Features::resetPasswords()),
            'status' => $request->session()->get('status'),
        ]));

        Fortify::resetPasswordView(fn (Request $request) => Inertia::render('auth/ResetPassword', [
            'email' => $request->email,
            'token' => $request->route('token'),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]));

        Fortify::requestPasswordResetLinkView(fn (Request $request) => Inertia::render('auth/ForgotPassword', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::verifyEmailView(fn (Request $request) => Inertia::render('auth/VerifyEmail', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::registerView(fn () => Inertia::render('auth/Register', [
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]));

        Fortify::twoFactorChallengeView(fn () => Inertia::render('auth/TwoFactorChallenge'));

        Fortify::confirmPasswordView(fn () => Inertia::render('auth/ConfirmPassword'));
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        // The limiters below guard the routes listed in ThrottleAuthenticationRequests.
        RateLimiter::for('register', function (Request $request) {
            return Limit::perHour(10)->by($request->ip());
        });

        RateLimiter::for('password-reset', function (Request $request) {
            return [
                Limit::perMinute(5)->by('minute:'.$request->ip()),
                Limit::perHour(20)->by('hour:'.$request->ip()),
            ];
        });

        RateLimiter::for('password-confirmation', function (Request $request) {
            return Limit::perMinute(5)->by($request->user()?->id ?: $request->ip());
        });
    }
}
