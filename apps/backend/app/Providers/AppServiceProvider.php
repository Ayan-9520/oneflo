<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(\App\Services\OrganizationContext::class);
        $this->app->scoped(\App\Services\AuthorizationService::class);
        $this->app->scoped(\App\Services\AuditLogger::class);
        $this->app->bind(\App\Services\DomainOwnershipCheck::class, \App\Services\DnsDomainOwnershipCheck::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Password::defaults(fn () => Password::min(12)->letters()->mixedCase()->numbers());
        RateLimiter::for('auth', function (Request $request): array {
            $email = strtolower((string) $request->input('email'));

            return [
                Limit::perMinute(10)->by($request->ip().'|'.$email),
                Limit::perMinute(60)->by($request->ip()),
            ];
        });
        RateLimiter::for('biometric', function (Request $request): Limit {
            return Limit::perMinute(30)->by($request->ip().'|'.$request->header('X-Organization-Id', 'none'));
        });
    }
}
