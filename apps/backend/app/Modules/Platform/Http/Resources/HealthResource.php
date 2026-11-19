<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Resources;

use App\Modules\Platform\Data\PlatformHealthReport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PlatformHealthReport */
final class HealthResource extends JsonResource
{
    /**
     * @return array{status: string, checks: list<array{name: string, status: string, detail: string|null}>}
     */
    public function toArray(Request $request): array
    {
        /** @var PlatformHealthReport $report */
        $report = $this->resource;

        return [
            'status' => $report->status,
            'checks' => $report->checks,
        ];
    }
}
