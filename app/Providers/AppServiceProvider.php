<?php

namespace App\Providers;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureFakeToday();
    }

    /**
     * Make the application run on the date set in APP_FAKE_TODAY, keeping the current time of day,
     * to try out how it behaves on other dates. Never in production nor in the test suite.
     */
    protected function configureFakeToday(): void
    {
        $fakeToday = config('app.fake_today');

        if (! is_string($fakeToday) || $fakeToday === '' || app()->isProduction() || app()->runningUnitTests()) {
            return;
        }

        $now = CarbonImmutable::parse($fakeToday)->setTimeFrom(CarbonImmutable::now());

        CarbonImmutable::setTestNow($now);
        Carbon::setTestNow($now);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
