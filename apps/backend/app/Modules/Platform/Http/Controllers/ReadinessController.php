<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Actions\CheckPlatformHealth;
use App\Modules\Platform\Http\Resources\HealthResource;
use Illuminate\Http\JsonResponse;

final class ReadinessController extends Controller
{
    public function __invoke(CheckPlatformHealth $checkPlatformHealth): JsonResponse
    {
        $report = $checkPlatformHealth->execute();

        return (new HealthResource($report))
            ->response()
            ->setStatusCode($report->status === 'ok' ? 200 : 503);
    }
}
