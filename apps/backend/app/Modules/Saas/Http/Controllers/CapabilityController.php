<?php

declare(strict_types=1);

namespace App\Modules\Saas\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AiProvider;
use App\Models\Employee;
use App\Models\IntegrationDelivery;
use App\Models\IntegrationProvider;
use App\Models\IssuedDocument;
use App\Models\JobOpening;
use App\Models\Organization;
use App\Models\WorkflowDefinition;
use App\Modules\Attendance\Services\AttendanceService;
use App\Modules\Saas\SaasService;
use App\Modules\Saas\WorkflowRunner;
use App\Services\AuthorizationService;
use App\Services\OrganizationContext;
use App\Support\ApiResponse;
use App\Support\Permissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;

class CapabilityController extends Controller
{
    public function providers(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $authorization->authorizePermission($request->user(), Permissions::INTEGRATIONS_MANAGE);

        return ApiResponse::data(IntegrationProvider::query()->orderBy('kind')->get()->map(fn (IntegrationProvider $provider) => [
            'id' => $provider->id,
            'kind' => $provider->kind,
            'endpoint' => $provider->endpoint,
            'enabled' => $provider->enabled,
        ])->all());
    }

    public function storeProvider(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $authorization->authorizePermission($request->user(), Permissions::INTEGRATIONS_MANAGE);
        $data = $request->validate([
            'kind' => ['required', Rule::in(['biometric', 'payroll', 'accounting'])],
            'endpoint' => ['required', 'url', 'starts_with:https://'],
            'secret' => ['required', 'string', 'max:200'],
            'enabled' => ['required', 'boolean'],
        ]);
        $provider = IntegrationProvider::query()->updateOrCreate(['kind' => $data['kind']], $data);

        return ApiResponse::data(['id' => $provider->id, 'kind' => $provider->kind, 'enabled' => $provider->enabled], 201);
    }

    public function deliveries(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $authorization->authorizePermission($request->user(), Permissions::INTEGRATIONS_MANAGE);

        return ApiResponse::data(IntegrationDelivery::query()->orderByDesc('id')->limit(50)->get(['id', 'kind', 'status', 'detail', 'subject'])->all());
    }

    public function biometric(Request $request, AttendanceService $attendance, OrganizationContext $context): JsonResponse
    {
        $organization = $this->biometricOrganization($request);
        $context->set($organization);
        $data = $request->validate([
            'employee_id' => ['required', 'integer'],
            'work_date' => ['required', 'date'],
            'punched_at' => ['required', 'date'],
            'kind' => ['required', Rule::in(['in', 'out'])],
            'work_mode' => ['required', 'string', 'max:40'],
        ]);
        $employee = Employee::query()->whereKey($data['employee_id'])->first();
        if ($employee === null || (int) $employee->organization_id !== $organization->id) {
            abort(404, 'The requested resource was not found.');
        }
        $day = $attendance->punch($employee, $data);

        return ApiResponse::data(['employee_id' => $employee->id, 'status' => $day->status], 201);
    }

    public function aiProvider(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $authorization->authorizePermission($request->user(), Permissions::INTEGRATIONS_MANAGE);
        $provider = AiProvider::query()->first();

        return ApiResponse::data($provider === null ? null : [
            'endpoint' => $provider->endpoint,
            'enabled' => $provider->enabled,
        ]);
    }

    public function storeAiProvider(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $authorization->authorizePermission($request->user(), Permissions::INTEGRATIONS_MANAGE);
        $data = $request->validate([
            'endpoint' => ['required', 'url', 'starts_with:https://'],
            'secret' => ['required', 'string', 'max:200'],
            'enabled' => ['required', 'boolean'],
        ]);
        $provider = AiProvider::query()->updateOrCreate([], $data);

        return ApiResponse::data(['id' => $provider->id, 'enabled' => $provider->enabled], 201);
    }

    public function ask(Request $request, AuthorizationService $authorization, OrganizationContext $context, SaasService $saas): JsonResponse
    {
        $authorization->authorizePermission($request->user(), Permissions::AI_USE);
        $data = $request->validate(['question' => ['required', 'string', 'max:1000']]);
        $organization = $context->get();
        $contextLines = ['organization_id: '.$organization->id];
        if ($authorization->hasPermission($request->user(), Permissions::REPORTS_VIEW, $organization)) {
            $attrition = $saas->attrition($organization);
            $contextLines[] = 'exits: '.$attrition['exits'];
            $contextLines[] = 'active_headcount: '.$attrition['headcount'];
        }
        if ($authorization->hasPermission($request->user(), Permissions::EMPLOYEES_VIEW, $organization)) {
            $contextLines[] = 'visible_employees: '.Employee::query()->count();
        }
        $answer = $this->complete($organization, $data['question'], $contextLines);

        return ApiResponse::data(['answer' => $answer, 'mode' => 'provider']);
    }

    public function documents(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $authorization->authorizePermission($request->user(), Permissions::AI_USE);
        $authorization->authorizePermission($request->user(), Permissions::DOCUMENTS_VIEW);
        $term = $request->validate(['q' => ['required', 'string', 'min:2', 'max:80']])['q'];
        $like = '%'.addcslashes($term, '%_\\').'%';
        $rows = IssuedDocument::query()
            ->where(function ($query) use ($like): void {
                $query->where('number', 'like', $like)->orWhere('html', 'like', $like);
            })
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        return ApiResponse::data([
            'mode' => 'stored-text',
            'matches' => $rows->map(fn (IssuedDocument $document) => [
                'id' => $document->id,
                'number' => $document->number,
                'document_type' => $document->document_type,
                'issued_on' => $document->issued_on?->toDateString(),
            ])->all(),
        ]);
    }

    public function recruitment(Request $request, AuthorizationService $authorization, OrganizationContext $context): JsonResponse
    {
        $authorization->authorizePermission($request->user(), Permissions::AI_USE);
        $authorization->authorizePermission($request->user(), Permissions::RECRUITMENT_VIEW);
        $data = $request->validate(['prompt' => ['required', 'string', 'max:1000']]);
        $organization = $context->get();
        $titles = JobOpening::query()->orderByDesc('id')->limit(20)->pluck('title')->all();
        $answer = $this->complete($organization, $data['prompt'], ['openings: '.implode(', ', $titles)]);

        return ApiResponse::data(['answer' => $answer, 'openings' => $titles, 'mode' => 'provider']);
    }

    public function workflows(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $authorization->authorizePermission($request->user(), Permissions::WORKFLOWS_MANAGE);

        return ApiResponse::data(WorkflowDefinition::query()->orderBy('name')->get(['id', 'name', 'event', 'steps', 'enabled'])->all());
    }

    public function storeWorkflow(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $authorization->authorizePermission($request->user(), Permissions::WORKFLOWS_MANAGE);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'event' => ['required', Rule::in(['leave.applied', 'expense.submitted'])],
            'steps' => ['required', 'array', 'min:1'],
            'steps.*.permission' => ['required', 'string', 'max:80'],
            'enabled' => ['required', 'boolean'],
        ]);
        $workflow = WorkflowDefinition::query()->create($data);

        return ApiResponse::data(['id' => $workflow->id], 201);
    }

    public function runWorkflow(Request $request, WorkflowDefinition $workflow, AuthorizationService $authorization, OrganizationContext $context, WorkflowRunner $runner): JsonResponse
    {
        $authorization->authorizePermission($request->user(), Permissions::WORKFLOWS_MANAGE);
        $data = $request->validate(['subject' => ['required', 'string', 'max:80']]);
        $runner->run($context->get(), $workflow, $data['subject']);

        return ApiResponse::data(['status' => 'ran']);
    }

    private function biometricOrganization(Request $request): Organization
    {
        $organizationId = $request->header('X-Organization-Id');
        if (! is_string($organizationId) || ! ctype_digit($organizationId)) {
            abort(422, 'Organization context is required.');
        }
        $organization = Organization::query()->find((int) $organizationId);
        if ($organization === null || $organization->status !== 'active') {
            abort(404, 'The requested resource was not found.');
        }
        $provider = IntegrationProvider::withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('kind', 'biometric')
            ->where('enabled', true)
            ->first();
        if ($provider === null) {
            abort(422, 'No biometric provider is enabled for this business.');
        }
        $token = $request->bearerToken();
        if (! is_string($token) || ! hash_equals($provider->secret, $token)) {
            abort(401, 'Authentication is required.');
        }

        return $organization;
    }

    /**
     * @param  list<string>  $contextLines
     */
    private function complete(Organization $organization, string $prompt, array $contextLines): string
    {
        $provider = AiProvider::query()->where('enabled', true)->first();
        if ($provider === null) {
            abort(422, 'No AI provider is enabled for this business.');
        }
        $response = Http::timeout(8)->withToken($provider->secret)->post($provider->endpoint, [
            'organization_id' => $organization->id,
            'prompt' => $prompt,
            'context' => $contextLines,
        ]);
        if (! $response->successful()) {
            abort(502, 'The AI provider did not accept the request.');
        }
        $answer = $response->json('answer');
        if (! is_string($answer) || $answer === '') {
            abort(502, 'The AI provider returned no answer.');
        }

        return $answer;
    }
}
