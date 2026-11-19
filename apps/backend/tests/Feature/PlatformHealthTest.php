<?php

namespace Tests\Feature;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

class PlatformHealthTest extends TestCase
{
    public function test_health_reports_the_process_is_alive(): void
    {
        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertJsonPath('data.status', 'ok');
    }

    public function test_readiness_reports_database_and_redis_when_both_respond(): void
    {
        Redis::shouldReceive('connection')->once()->andReturn(new class
        {
            public function ping(): string
            {
                return 'PONG';
            }
        });

        $this->getJson('/api/v1/ready')
            ->assertOk()
            ->assertJsonPath('data.status', 'ok')
            ->assertJsonPath('data.checks.0.name', 'database')
            ->assertJsonPath('data.checks.0.status', 'ok')
            ->assertJsonPath('data.checks.1.name', 'redis')
            ->assertJsonPath('data.checks.1.status', 'ok');
    }

    public function test_readiness_returns_service_unavailable_when_redis_fails(): void
    {
        Redis::shouldReceive('connection')->once()->andThrow(new RuntimeException('connection refused'));

        $this->getJson('/api/v1/ready')
            ->assertStatus(503)
            ->assertJsonPath('data.status', 'degraded')
            ->assertJsonPath('data.checks.0.status', 'ok')
            ->assertJsonPath('data.checks.1.status', 'failed')
            ->assertJsonPath('data.checks.1.detail', 'The service did not respond.');
    }

    public function test_unknown_api_routes_return_json(): void
    {
        $this->getJson('/api/v1/missing')
            ->assertNotFound()
            ->assertJsonPath('message', 'The requested resource was not found.');
    }

    public function test_form_requests_return_field_errors(): void
    {
        Route::post('/api/v1/_validation', function (SampleValidationRequest $request) {
            return response()->json(['data' => $request->validated()]);
        });

        $this->postJson('/api/v1/_validation', [])
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'errors' => ['name']]);
    }
}

class SampleValidationRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string'],
        ];
    }
}
