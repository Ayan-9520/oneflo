<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Subscription;
use App\Services\OrganizationContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforcePlan
{
    public function __construct(private readonly OrganizationContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $organization = $this->context->get();
        if ($organization === null || $this->exempt($request)) {
            return $next($request);
        }
        $subscription = Subscription::query()->with('plan')->first();
        if ($subscription === null) {
            return $next($request);
        }
        if ($subscription->status === 'trial' && $subscription->trial_ends_on !== null && $subscription->trial_ends_on->isPast()) {
            $subscription->status = 'expired';
            $subscription->save();
        }
        $feature = $this->feature($request->path());
        if ($feature === null) {
            return $next($request);
        }
        if (in_array($subscription->status, ['expired', 'cancelled'], true)) {
            abort(403, 'You are not allowed to perform this action.');
        }
        $features = $subscription->plan?->features ?? [];
        if (! in_array($feature, $features, true)) {
            abort(403, 'You are not allowed to perform this action.');
        }

        return $next($request);
    }

    private function exempt(Request $request): bool
    {
        $path = $request->path();

        return str_contains($path, '/subscription')
            || str_contains($path, '/billing-invoices')
            || str_contains($path, '/usage')
            || str_contains($path, '/tenant-onboarding');
    }

    private function feature(string $path): ?string
    {
        $map = [
            '/attendance' => 'attendance',
            '/leave' => 'leave',
            '/payroll' => 'payroll',
            '/issued-documents' => 'documents',
            '/document-' => 'documents',
            '/company-profiles' => 'documents',
            '/recruitment' => 'recruitment',
            '/onboarding' => 'recruitment',
            '/performance' => 'performance',
            '/assets' => 'assets',
            '/expenses' => 'expenses',
            '/helpdesk' => 'helpdesk',
            '/engagement' => 'engagement',
            '/reports' => 'reports',
            '/attrition' => 'analytics',
            '/ai/' => 'ai',
            '/workflows' => 'workflows',
            '/integration-providers' => 'integrations',
            '/integrations/' => 'integrations',
        ];
        foreach ($map as $needle => $feature) {
            if (str_contains($path, $needle)) {
                return $feature;
            }
        }

        return null;
    }
}
