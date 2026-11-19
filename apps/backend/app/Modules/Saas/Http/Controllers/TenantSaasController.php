<?php

declare(strict_types=1);

namespace App\Modules\Saas\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\BillingInvoice;
use App\Models\Subscription;
use App\Modules\Saas\SaasService;
use App\Services\AuthorizationService;
use App\Services\OrganizationContext;
use App\Support\ApiResponse;
use App\Support\Permissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantSaasController extends Controller
{
    public function subscription(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $authorization->authorizePermission($request->user(), Permissions::BILLING_VIEW);
        $subscription = Subscription::query()->with('plan')->first();

        return ApiResponse::data($subscription === null ? null : [
            'status' => $subscription->status,
            'trial_ends_on' => $subscription->trial_ends_on?->toDateString(),
            'current_period_start' => $subscription->current_period_start?->toDateString(),
            'current_period_end' => $subscription->current_period_end?->toDateString(),
            'plan' => [
                'name' => $subscription->plan?->name,
                'interval' => $subscription->plan?->interval,
                'features' => $subscription->plan?->features ?? [],
                'employee_limit' => $subscription->plan?->employee_limit,
                'price_rupees' => $subscription->plan?->price_paise === null ? null : number_format($subscription->plan->price_paise / 100, 2, '.', ''),
                'currency' => $subscription->plan?->currency,
            ],
        ]);
    }

    public function invoices(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $authorization->authorizePermission($request->user(), Permissions::BILLING_VIEW);

        return ApiResponse::data(BillingInvoice::query()->orderByDesc('id')->limit(50)->get()->map(fn (BillingInvoice $invoice) => [
            'id' => $invoice->id,
            'amount_rupees' => number_format($invoice->amount_paise / 100, 2, '.', ''),
            'currency' => $invoice->currency,
            'period_start' => $invoice->period_start?->toDateString(),
            'period_end' => $invoice->period_end?->toDateString(),
            'status' => $invoice->status,
            'reference' => $invoice->reference,
        ])->all());
    }

    public function renew(Request $request, SaasService $saas, AuthorizationService $authorization): JsonResponse
    {
        $authorization->authorizePermission($request->user(), Permissions::BILLING_VIEW);
        $subscription = Subscription::query()->with('plan')->first();
        if ($subscription === null) {
            abort(404, 'The requested resource was not found.');
        }
        $invoice = $saas->renew($subscription, $request->user());

        return ApiResponse::data([
            'invoice_id' => $invoice->id,
            'amount_rupees' => number_format($invoice->amount_paise / 100, 2, '.', ''),
            'currency' => $invoice->currency,
        ], 201);
    }

    public function usage(Request $request, SaasService $saas, AuthorizationService $authorization, OrganizationContext $context): JsonResponse
    {
        $authorization->authorizePermission($request->user(), Permissions::BILLING_VIEW);

        return ApiResponse::data($saas->usage($context->get()));
    }

    public function onboarding(Request $request, SaasService $saas, AuthorizationService $authorization): JsonResponse
    {
        $authorization->authorizePermission($request->user(), Permissions::BILLING_VIEW);

        return ApiResponse::data($saas->onboarding());
    }

    public function analytics(Request $request, SaasService $saas, AuthorizationService $authorization, OrganizationContext $context): JsonResponse
    {
        $authorization->authorizePermission($request->user(), Permissions::REPORTS_VIEW);
        $organization = $context->get();

        return ApiResponse::data([
            ...$saas->usage($organization),
            'attrition' => $saas->attrition($organization),
        ]);
    }

    public function attrition(Request $request, SaasService $saas, AuthorizationService $authorization, OrganizationContext $context): JsonResponse
    {
        $authorization->authorizePermission($request->user(), Permissions::REPORTS_VIEW);

        return ApiResponse::data($saas->attrition($context->get()));
    }
}
