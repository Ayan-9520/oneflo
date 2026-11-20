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
        RateLimiter::for('careers', function (Request $request): Limit {
            return $request->isMethod('post')
                ? Limit::perMinute(5)->by('apply|'.$request->ip())
                : Limit::perMinute(120)->by('browse|'.$request->ip());
        });
        RateLimiter::for('candidate-code', function (Request $request): array {
            return [
                Limit::perMinute(3)->by('code-ip|'.$request->ip()),
                Limit::perHour(10)->by('code-dest|'.strtolower(trim((string) $request->input('destination')))),
            ];
        });
        RateLimiter::for('candidate-auth', function (Request $request): Limit {
            return Limit::perMinute(10)->by('cand-auth|'.$request->ip());
        });
        RateLimiter::for('candidate-import', function (Request $request): Limit {
            return Limit::perMinute(10)->by('import|'.($request->user()?->id ?? $request->ip()));
        });
        RateLimiter::for('biometric', function (Request $request): Limit {
            return Limit::perMinute(30)->by($request->ip().'|'.$request->header('X-Organization-Id', 'none'));
        });
    }
}
