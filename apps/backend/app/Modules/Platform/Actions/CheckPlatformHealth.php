<?php

declare(strict_types=1);

namespace App\Modules\Platform\Actions;

use App\Modules\Platform\Data\PlatformHealthReport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Throwable;

final class CheckPlatformHealth
{
    public function execute(): PlatformHealthReport
    {
        $checks = [
            $this->probe('database', function (): void {
                DB::connection()->select('select 1 as ok');
            }),
            $this->probe('redis', function (): void {
                $pong = Redis::connection()->ping();

                if ($pong !== true && $pong !== 'PONG' && $pong !== '+PONG') {
                    throw new \RuntimeException('Redis ping returned an unexpected response.');
                }
            }),
        ];

        $healthy = collect($checks)->every(
            fn (array $check): bool => $check['status'] === 'ok',
        );

        return new PlatformHealthReport(
            status: $healthy ? 'ok' : 'degraded',
            checks: $checks,
        );
    }

    /**
     * @param  callable(): void  $probe
     * @return array{name: string, status: string, detail: string|null}
     */
    private function probe(string $name, callable $probe): array
    {
        try {
            $probe();

            return [
                'name' => $name,
                'status' => 'ok',
                'detail' => null,
            ];
        } catch (Throwable $exception) {
            Log::error('platform.health.failed', [
                'check' => $name,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return [
                'name' => $name,
                'status' => 'failed',
                'detail' => 'The service did not respond.',
            ];
        }
    }
}
