<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeReferral;
use App\Models\JobOpening;
use App\Models\ReferralReward;
use App\Modules\Recruitment\Services\CandidateAuthService;
use App\Modules\Recruitment\Services\CareersService;
use App\Services\AuthorizationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CareersController extends Controller
{
    public function index(Request $request, CareersService $careers): JsonResponse
    {
        return ApiResponse::data([
            'companies' => $careers->companies(),
            'categories' => $careers->categories(),
            'facets' => $careers->facets(),
            'teams' => $careers->teams(),
            'jobs' => $careers->jobs($request->query()),
        ]);
    }

    public function logo(string $slug, CareersService $careers, \App\Modules\Documents\Services\BrandingFileStore $files): \Symfony\Component\HttpFoundation\Response
    {
        $path = $careers->logo($slug);
        if ($path === null || ! preg_match('/\.(png|jpe?g|webp|svg)$/i', $path)) {
            abort(404, 'The requested resource was not found.');
        }
        $response = $files->response($path);
        $response->headers->set('Cache-Control', 'public, max-age=3600');
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }

    public function close(Request $request, JobOpening $jobOpening, AuthorizationService $authorization, CareersService $careers): JsonResponse
    {
        $authorization->authorizePermission($request->user(), 'recruitment.manage');
        $data = $request->validate(['status' => ['required', Rule::in(['closed', 'archived'])]]);
        $opening = $careers->close($jobOpening, $request->user(), $data['status']);

        return ApiResponse::data(['id' => $opening->id, 'status' => $opening->status]);
    }

    public function company(string $slug, Request $request, CareersService $careers): JsonResponse
    {
        $company = collect($careers->companies())->firstWhere('slug', $slug);
        if ($company === null) {
            abort(404, 'The requested resource was not found.');
        }

        return ApiResponse::data(['company' => $company, 'jobs' => $careers->jobs([...$request->query(), 'company' => $slug])]);
    }

    public function show(string $slug, CareersService $careers): JsonResponse
    {
        return ApiResponse::data($careers->job($slug));
    }

    public function apply(Request $request, string $slug, CareersService $careers): JsonResponse
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email', 'max:160'],
            'phone' => ['required', 'string', 'max:30'],
            'location' => ['required', 'string', 'max:120'],
            'experience_years' => ['required', 'integer', 'between:0,60'],
            'current_company' => ['nullable', 'string', 'max:160'],
            'current_designation' => ['nullable', 'string', 'max:160'],
            'current_salary' => ['nullable', 'string', 'max:80'],
            'expected_salary' => ['nullable', 'string', 'max:80'],
            'notice_period' => ['required', 'string', 'max:80'],
            'qualification' => ['required', 'string', 'max:160'],
            'skills' => ['required', 'string', 'max:1000'],
            'source' => ['nullable', 'string', 'max:80'],
            'message' => ['nullable', 'string', 'max:2000'],
            'consent' => ['required', 'accepted'],
            'accurate' => ['required', 'accepted'],
            'ref' => ['nullable', 'string', 'max:80'],
            'utm_source' => ['nullable', 'string', 'max:80'],
            'utm_medium' => ['nullable', 'string', 'max:80'],
            'utm_campaign' => ['nullable', 'string', 'max:80'],
            'utm_content' => ['nullable', 'string', 'max:80'],
            'organization_id' => ['prohibited'],
            'resume' => ['required', 'file', 'mimes:pdf,doc,docx', 'max:5120'],
        ]);
        $account = app(CandidateAuthService::class)->resolve($request->bearerToken());
        $result = $careers->apply($slug, $data, $request->file('resume'), $account);

        return ApiResponse::data($result, 201);
    }

    public function publish(Request $request, JobOpening $jobOpening, AuthorizationService $authorization, CareersService $careers): JsonResponse
    {
        $authorization->authorizePermission($request->user(), 'recruitment.manage');
        $data = $request->validate([
            'location' => ['required', 'string', 'max:120'],
            'employment_type' => ['required', Rule::in(['full_time', 'part_time', 'contract', 'intern'])],
            'category' => ['required', Rule::in(CareersService::CATEGORIES)],
            'experience_min' => ['nullable', 'integer', 'between:0,60'],
            'experience_max' => ['nullable', 'integer', 'between:0,60'],
            'salary_public' => ['nullable', 'boolean'],
            'salary_note' => ['nullable', 'string', 'max:160'],
            'show_headcount' => ['nullable', 'boolean'],
            'responsibilities' => ['nullable', 'string'],
            'qualifications' => ['nullable', 'string'],
            'skills' => ['nullable', 'string'],
            'benefits' => ['nullable', 'string'],
            'deadline_on' => ['nullable', 'date'],
            'description' => ['nullable', 'string', 'max:10000'],
        ]);
        $opening = $careers->publish($jobOpening, $data, $request->user());

        return ApiResponse::data(['id' => $opening->id, 'slug' => $opening->slug, 'is_public' => $opening->is_public]);
    }

    public function unpublish(Request $request, JobOpening $jobOpening, AuthorizationService $authorization, CareersService $careers): JsonResponse
    {
        $authorization->authorizePermission($request->user(), 'recruitment.manage');
        $opening = $careers->unpublish($jobOpening, $request->user());

        return ApiResponse::data(['id' => $opening->id, 'is_public' => $opening->is_public]);
    }

    public function myReferrals(Request $request): JsonResponse
    {
        $employee = Employee::query()->where('user_id', $request->user()->id)->first();
        $rows = $employee === null ? [] : EmployeeReferral::query()->with('opening')->where('employee_id', $employee->id)->orderByDesc('id')->get()->map(fn (EmployeeReferral $referral) => [
            'id' => $referral->id,
            'job_title' => $referral->opening?->title,
            'status' => $referral->status,
            'token' => $referral->token,
            'slug' => $referral->opening?->slug,
            'created_at' => $referral->created_at?->toDateString(),
        ])->all();

        return ApiResponse::data($rows);
    }

    public function storeReferral(Request $request, CareersService $careers): JsonResponse
    {
        $employee = Employee::query()->where('user_id', $request->user()->id)->first();
        if ($employee === null) {
            return ApiResponse::error('No employee record is linked to this account.', 422);
        }
        $data = $request->validate(['job_opening_id' => ['required', 'integer']]);
        $opening = JobOpening::query()->whereKey($data['job_opening_id'])->where('is_public', true)->first();
        if ($opening === null) {
            abort(404, 'The requested resource was not found.');
        }
        $referral = $careers->referralLink($employee, $opening);

        return ApiResponse::data(['token' => $referral->token, 'slug' => $opening->slug], 201);
    }

    public function referrals(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $authorization->authorizePermission($request->user(), 'recruitment.view');

        return ApiResponse::data(EmployeeReferral::query()->with('opening')->orderByDesc('id')->get()->map(fn (EmployeeReferral $referral) => [
            'id' => $referral->id,
            'employee_id' => $referral->employee_id,
            'job_title' => $referral->opening?->title,
            'status' => $referral->status,
            'created_at' => $referral->created_at?->toDateString(),
        ])->all());
    }

    public function savePolicy(Request $request, AuthorizationService $authorization, CareersService $careers): JsonResponse
    {
        $authorization->authorizePermission($request->user(), 'recruitment.rewards.manage');
        $data = $request->validate([
            'job_opening_id' => ['nullable', 'integer'],
            'enabled' => ['required', 'boolean'],
            'amount' => ['required', 'integer', 'min:0'],
            'milestone' => ['required', Rule::in(['selected', 'joined', 'probation'])],
        ]);
        $policy = $careers->savePolicy($data, $request->user());

        return ApiResponse::data(['id' => $policy->id, 'enabled' => $policy->enabled]);
    }

    public function decideReward(Request $request, ReferralReward $referralReward, AuthorizationService $authorization, CareersService $careers): JsonResponse
    {
        $authorization->authorizePermission($request->user(), 'recruitment.rewards.manage');
        $data = $request->validate([
            'status' => ['required', Rule::in(['eligible', 'approved', 'rejected'])],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);
        $reward = $careers->decideReward($referralReward, $request->user(), $data['status'], $data['notes'] ?? null);

        return ApiResponse::data(['id' => $reward->id, 'status' => $reward->status, 'payout_status' => $reward->payout_status]);
    }
}
