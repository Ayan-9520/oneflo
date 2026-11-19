<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Organization;
use App\Services\OrganizationContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireOrganizationContext
{
    public function __construct(private readonly OrganizationContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $organizationId = $request->header('X-Organization-Id');
        $locked = $this->context->locked();

        if ($user === null) {
            abort(401, 'Authentication is required.');
        }

        if ($locked !== null) {
            if (! is_string($organizationId) || $organizationId === '') {
                $organizationId = (string) $locked->id;
            } elseif (! ctype_digit($organizationId) || (int) $organizationId !== $locked->id) {
                abort(403, 'You are not allowed to perform this action.');
            }
        }

        if (! is_string($organizationId) || $organizationId === '') {
            abort(422, 'Organization context is required.');
        }

        if (! ctype_digit($organizationId)) {
            abort(422, 'Organization context is required.');
        }

        $organization = Organization::query()->find((int) $organizationId);

        if ($organization === null) {
            abort(404, 'The requested resource was not found.');
        }

        if (! app(\App\Services\AuthorizationService::class)->canEnter($user, $organization)) {
            abort(403, 'You are not allowed to perform this action.');
        }

        $this->context->set($organization);

        return $next($request);
    }
}
