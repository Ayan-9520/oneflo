<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\OrganizationContext;
use App\Support\ApiResponse;
use App\Support\HostName;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class TenantDomainController extends Controller
{
    public function __invoke(Request $request, OrganizationContext $context): JsonResponse
    {
        $locked = $context->locked();

        return ApiResponse::data([
            'host' => HostName::normalize($request->getHost()),
            'locked' => $locked !== null,
            'organization' => $locked === null ? null : [
                'id' => $locked->id,
                'name' => $locked->name,
            ],
        ]);
    }
}
