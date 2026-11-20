<?php

namespace App\Http\Middleware;

use App\Modules\Recruitment\Services\CandidateAuthService;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateCandidate
{
    public function __construct(private readonly CandidateAuthService $auth) {}

    public function handle(Request $request, Closure $next): Response
    {
        $account = $this->auth->resolve($request->bearerToken());
        if ($account === null) {
            return ApiResponse::error('Please sign in to continue.', 401);
        }
        $request->attributes->set('candidate_account', $account);

        return $next($request);
    }
}
