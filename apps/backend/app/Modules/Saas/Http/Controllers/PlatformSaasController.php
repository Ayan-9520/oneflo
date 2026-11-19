<?php

declare(strict_types=1);

namespace App\Modules\Saas\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\OrganizationRegistration;
use App\Models\SubscriptionPlan;
use App\Modules\Saas\SaasService;
use App\Services\AuthorizationService;
use App\Support\ApiResponse;
use App\Support\Permissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PlatformSaasController extends Controller
{
    public function register(Request $request, SaasService $saas): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:120'],
        ]);
        $registration = $saas->register($request->user(), $data['name'], $data['slug'] ?? null);

        return ApiResponse::data([
            'id' => $registration->id,
            'status' => $registration->status,
            'organization_id' => $registration->organization_id,
            'organization' => $registration->organization?->only(['id', 'name', 'slug', 'status']),
        ], 201);
    }

    public function registrations(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $manage = $authorization->hasPermission($request->user(), Permissions::BILLING_MANAGE);
        $query = OrganizationRegistration::withoutGlobalScopes()->with(['organization', 'applicant'])->orderByDesc('id');
        if (! $manage) {
            $query->where('user_id', $request->user()->id);
        }

        return ApiResponse::data($query->limit(100)->get()->map(fn (OrganizationRegistration $row) => [
            'id' => $row->id,
            'status' => $row->status,
            'organization_id' => $row->organization_id,
            'organization' => $row->organization?->name,
            'applicant' => $manage ? $row->applicant?->email : null,
            'note' => $row->note,
        ])->all());
    }

    public function decision(Request $request, int $registration, SaasService $saas, AuthorizationService $authorization): JsonResponse
    {
        $this->manage($request, $authorization);
        $row = OrganizationRegistration::withoutGlobalScopes()->find($registration);
        if ($row === null) {
            abort(404, 'The requested resource was not found.');
        }
        $data = $request->validate([
            'approve' => ['required', 'boolean'],
            'plan_id' => ['required_if:approve,true', 'nullable', 'integer'],
            'note' => ['nullable', 'string', 'max:240'],
        ]);
        if ($data['approve']) {
            $plan = SubscriptionPlan::query()->find($data['plan_id'] ?? null);
            if ($plan === null) {
                abort(404, 'The requested resource was not found.');
            }
            $subscription = $saas->approve($row, $plan, $request->user());

            return ApiResponse::data(['status' => 'approved', 'subscription_status' => $subscription->status]);
        }
        $saas->reject($row, $request->user(), $data['note'] ?? null);

        return ApiResponse::data(['status' => 'rejected']);
    }

    public function plans(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $this->manage($request, $authorization);

        return ApiResponse::data(SubscriptionPlan::query()->orderBy('name')->get()->map(fn (SubscriptionPlan $plan) => $this->planPayload($plan))->all());
    }

    public function storePlan(Request $request, SaasService $saas, AuthorizationService $authorization): JsonResponse
    {
        $this->manage($request, $authorization);
        $data = $this->planData($request, $saas);
        $plan = SubscriptionPlan::query()->create($data);

        return ApiResponse::data($this->planPayload($plan), 201);
    }

    public function updatePlan(Request $request, SubscriptionPlan $plan, SaasService $saas, AuthorizationService $authorization): JsonResponse
    {
        $this->manage($request, $authorization);
        $plan->fill($this->planData($request, $saas, $plan->id));
        $plan->save();

        return ApiResponse::data($this->planPayload($plan));
    }

    public function activation(Request $request, Organization $organization, SaasService $saas, AuthorizationService $authorization): JsonResponse
    {
        $this->manage($request, $authorization);
        $data = $request->validate(['status' => ['required', Rule::in(['active', 'inactive'])]]);
        $organization = $saas->setActivation($organization, $data['status'], $request->user());

        return ApiResponse::data(['id' => $organization->id, 'status' => $organization->status]);
    }

    private function manage(Request $request, AuthorizationService $authorization): void
    {
        if (! $authorization->hasPermission($request->user(), Permissions::BILLING_MANAGE)) {
            abort(403, 'You are not allowed to perform this action.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function planData(Request $request, SaasService $saas, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:40', Rule::unique('subscription_plans', 'code')->ignore($ignoreId)],
            'name' => ['required', 'string', 'max:120'],
            'interval' => ['required', Rule::in(['monthly', 'yearly'])],
            'trial_days' => ['required', 'integer', 'min:0', 'max:365'],
            'price_rupees' => ['nullable', 'regex:/^\d+(\.\d{1,2})?$/'],
            'currency' => ['required', 'regex:/^[A-Z]{3}$/'],
            'employee_limit' => ['nullable', 'integer', 'min:1'],
            'features' => ['required', 'array', 'min:1'],
            'features.*' => ['string'],
            'status' => ['required', Rule::in(['active', 'retired'])],
        ]);
        $saas->assertFeatures($data['features']);
        $data['price_paise'] = $this->paise($data['price_rupees'] ?? null);
        unset($data['price_rupees']);

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function planPayload(SubscriptionPlan $plan): array
    {
        return [
            'id' => $plan->id,
            'code' => $plan->code,
            'name' => $plan->name,
            'interval' => $plan->interval,
            'trial_days' => $plan->trial_days,
            'price_rupees' => $plan->price_paise === null ? null : number_format($plan->price_paise / 100, 2, '.', ''),
            'currency' => $plan->currency,
            'employee_limit' => $plan->employee_limit,
            'features' => $plan->features,
            'status' => $plan->status,
        ];
    }

    private function paise(?string $rupees): ?int
    {
        if ($rupees === null || $rupees === '') {
            return null;
        }
        [$whole, $fraction] = array_pad(explode('.', $rupees, 2), 2, '0');

        return ((int) $whole) * 100 + (int) substr(str_pad($fraction, 2, '0'), 0, 2);
    }
}
