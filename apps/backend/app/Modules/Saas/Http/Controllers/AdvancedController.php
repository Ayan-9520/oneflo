<?php

declare(strict_types=1);

namespace App\Modules\Saas\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AiProvider;
use App\Models\Employee;
use App\Models\IntegrationProvider;
use App\Models\IssuedDocument;
use App\Models\JobApplication;
use App\Models\JobOpening;
use App\Models\Organization;
use App\Models\Subscription;
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

class AdvancedController extends Controller
{
    public function ask(Request $request, AuthorizationService $authorization, SaasService $saas, OrganizationContext $context): JsonResponse
    {
        $authorization->authorizePermission($request->user(), Permissions::AI_USE);
        $data = $request->validate(['question' => ['required', 'string', 'max:500']]);
        $provider = AiProvider::query()->first();
        if ($provider === null || ! $provider->enabled) {
            abort(422, 'The assistant provider is not enabled for this business.');
        }
        if (! str_starts_with($provider->endpoint, 'https://')) {
            abort(422, 'The assistant endpoint must use HTTPS.');
        }
        $organization = $context->get();
        $attrition = $saas->attrition($organization);
        $response = Http::timeout(5)->withToken($provider->secret)->post($provider->endpoint, [
            'organization_id' => $organization->id,
            'question' => $data['question'],
            'active_employees' => $attrition['headcount'],
            'exits_last_year' => $attrition['exits'],
        ]);
        if (! $response->successful()) {
            abort(422, 'The assistant provider returned HTTP '.$response->status().'.');
        }

        return ApiResponse::data(['mode' => 'provider', 'answer' => (string) ($response->json('answer') ?? '')]);
    }

    public function documents(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $authorization->authorizePermission($request->user(), Permissions::AI_USE);
        $authorization->authorizePermission($request->user(), Permissions::DOCUMENTS_VIEW);
        $data = $request->validate(['q' => ['required', 'string', 'min:2', 'max:80']]);
        $term = '%'.$data['q'].'%';
        $matches = IssuedDocument::query()
            ->where(function ($query) use ($term): void {
                $query->where('number', 'like', $term)->orWhere('html', 'like', $term);
            })
            ->orderByDesc('id')
            ->limit(20)
            ->get()
            ->map(fn (IssuedDocument $document) => [
                'id' => $document->id,
                'number' => $document->number,
                'document_type' => $document->document_type,
            ])->all();

        return ApiResponse::data(['mode' => 'stored-text', 'matches' => $matches]);
    }

    public function recruitment(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $authorization->authorizePermission($request->user(), Permissions::AI_USE);
        $authorization->authorizePermission($request->user(), Permissions::RECRUITMENT_VIEW);
        $openings = JobOpening::query()->orderByDesc('id')->limit(20)->get()->map(function (JobOpening $opening): array {
            return [
                'id' => $opening->id,
                'title' => $opening->title,
                'status' => $opening->status,
                'applications' => JobApplication::query()->where('job_opening_id', $opening->id)->count(),
            ];
        })->all();

        return ApiResponse::data(['mode' => 'stored-records', 'openings' => $openings]);
    }

    public function saveProvider(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $authorization->authorizePermission($request->user(), Permissions::AI_USE);
        $data = $request->validate([
            'endpoint' => ['required', 'url', 'starts_with:https://'],
            'secret' => ['required', 'string', 'max:200'],
            'enabled' => ['required', 'boolean'],
        ]);
        $provider = AiProvider::query()->first() ?? new AiProvider;
        $provider->fill($data);
        $provider->save();

        return ApiResponse::data(['enabled' => $provider->enabled], 201);
    }

    public function storeIntegration(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $authorization->authorizePermission($request->user(), Permissions::INTEGRATIONS_MANAGE);
        $data = $request->validate([
            'kind' => ['required', Rule::in(['biometric', 'payroll', 'accounting'])],
            'endpoint' => ['required', 'url', 'starts_with:https://'],
            'secret' => ['required', 'string', 'max:200'],
            'enabled' => ['required', 'boolean'],
        ]);
        $provider = IntegrationProvider::query()->firstOrNew(['kind' => $data['kind']]);
        $provider->fill($data);
        $provider->save();

        return ApiResponse::data(['id' => $provider->id, 'kind' => $provider->kind], 201);
    }

    public function biometric(Request $request, AttendanceService $attendance, OrganizationContext $context): JsonResponse
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
        $secret = $request->bearerToken();
        if ($provider === null || $secret === null || ! hash_equals($provider->secret, $secret)) {
            abort(401, 'Authentication is required.');
        }
        $context->set($organization);
        $subscription = Subscription::query()->with('plan')->first();
        if ($subscription !== null) {
            if ($subscription->status === 'trial' && $subscription->trial_ends_on !== null && $subscription->trial_ends_on->isPast()) {
                $subscription->status = 'expired';
                $subscription->save();
            }
            $features = $subscription->plan?->features ?? [];
            if (in_array($subscription->status, ['expired', 'cancelled'], true) || ! in_array('attendance', $features, true)) {
                abort(403, 'You are not allowed to perform this action.');
            }
        }
        $data = $request->validate([
            'employee_id' => ['required', 'integer'],
            'work_date' => ['required', 'date'],
            'punched_at' => ['required', 'date'],
            'kind' => ['required', Rule::in(['in', 'out', 'break_start', 'break_end'])],
            'work_mode' => ['required', Rule::in(['office', 'remote', 'hybrid', 'field'])],
        ]);
        $employee = Employee::query()->find($data['employee_id']);
        if ($employee === null) {
            abort(404, 'The requested resource was not found.');
        }
        $day = $attendance->punch($employee, $data);

        return ApiResponse::data(['employee_id' => $employee->id, 'status' => $day->status], 201);
    }

    public function storeWorkflow(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $authorization->authorizePermission($request->user(), Permissions::WORKFLOWS_MANAGE);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'event' => ['required', Rule::in(['leave.applied', 'expense.submitted', 'attendance.exception', 'payroll.published'])],
            'steps' => ['required', 'array', 'min:1'],
            'steps.*.permission' => ['required', 'string', Rule::in(array_keys(Permissions::catalog()))],
            'enabled' => ['required', 'boolean'],
        ]);
        $workflow = WorkflowDefinition::query()->create($data);

        return ApiResponse::data(['id' => $workflow->id], 201);
    }

    public function runWorkflow(Request $request, WorkflowDefinition $workflow, AuthorizationService $authorization, WorkflowRunner $runner, OrganizationContext $context): JsonResponse
    {
        $authorization->authorizePermission($request->user(), Permissions::WORKFLOWS_MANAGE);
        $data = $request->validate(['subject' => ['required', 'string', 'max:80']]);
        $runner->run($context->get(), $workflow, $data['subject']);

        return ApiResponse::data(['status' => 'ran']);
    }
}
