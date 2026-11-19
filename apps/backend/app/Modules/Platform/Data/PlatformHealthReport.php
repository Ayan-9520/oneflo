<?php

declare(strict_types=1);

namespace App\Modules\Platform\Data;

final class PlatformHealthReport
{
    /**
     * @param  list<array{name: string, status: string, detail: string|null}>  $checks
     */
    public function __construct(
        public string $status,
        public array $checks,
    ) {}
}
