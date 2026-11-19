<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\OrganizationDomain;
use App\Services\OrganizationContext;
use App\Support\HostName;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenantDomain
{
    public function __construct(private readonly OrganizationContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->context->forget();
        $host = HostName::normalize($request->getHost());
        if ($host === null) {
            abort(421, 'This domain is not a verified OneFlo address.');
        }

        if (HostName::isPlatform($host)) {
            return $next($request);
        }

        $domain = OrganizationDomain::query()
            ->where('host', $host)
            ->where('status', 'verified')
            ->first();

        if ($domain === null || $domain->organization === null) {
            abort(421, 'This domain is not a verified OneFlo address.');
        }

        $this->context->lock($domain->organization);
        $stateful = config('sanctum.stateful', []);
        if (is_array($stateful) && ! in_array($host, $stateful, true)) {
            $stateful[] = $host;
            config(['sanctum.stateful' => $stateful]);
        }

        return $next($request);
    }
}
