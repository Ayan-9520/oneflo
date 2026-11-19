<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Modules\Saas\FeatureGate;
use App\Services\OrganizationContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlanFeature
{
    public function __construct(
        private readonly OrganizationContext $context,
        private readonly FeatureGate $features,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $organization = $this->context->get();
        $feature = $this->feature($request->path());
        if ($organization !== null && $feature !== null && ! $request->user()?->hasPlatformRole()) {
            $this->features->assert($organization, $feature);
        }

        return $next($request);
    }

    private function feature(string $path): ?string
    {
        $segment = explode('/', str($path)->after('api/v1/')->toString())[0] ?? '';

        return match ($segment) {
            'attendance' => 'attendance',
            'leave' => 'leave',
            'payroll' => 'payroll',
            'recruitment', 'onboarding' => 'recruitment',
            'performance-reviews', 'goals', 'kpis' => 'performance',
            'assets' => 'assets',
            'expenses', 'expense-categories' => 'expenses',
            'tickets', 'ticket-categories', 'sla-policies' => 'helpdesk',
            'posts', 'surveys', 'recognitions' => 'engagement',
            'reports' => 'reports',
            'issued-documents', 'document-templates', 'document-previews' => 'documents',
            'analytics', 'attrition' => 'analytics',
            'ai' => 'ai',
            'workflows' => 'workflows',
            'integration-providers', 'integration-deliveries' => 'integrations',
            default => null,
        };
    }
}
