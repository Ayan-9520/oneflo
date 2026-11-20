<?php

namespace App\Modules\Recruitment\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\CandidateAccount;
use App\Models\JobApplication;
use App\Models\JobOpening;
use App\Models\Organization;
use App\Modules\Recruitment\Services\CandidateAuthService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class CandidateAccountController extends Controller
{
    public function methods(CandidateAuthService $auth): JsonResponse
    {
        return ApiResponse::data($auth->methods());
    }

    public function redirect(string $provider, CandidateAuthService $auth): JsonResponse
    {
        return ApiResponse::data(['url' => $auth->redirectUrl($provider)]);
    }

    public function callback(Request $request, string $provider, CandidateAuthService $auth): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:2000'],
            'state' => ['required', 'string', 'max:100'],
        ]);

        return $this->session($auth->completeOAuth($provider, $data['code'], $data['state']));
    }

    public function sendCode(Request $request, CandidateAuthService $auth): JsonResponse
    {
        $data = $request->validate([
            'channel' => ['required', Rule::in(['email', 'phone'])],
            'destination' => ['required', 'string', 'max:160'],
            'purpose' => ['nullable', Rule::in(['sign_in', 'register'])],
        ]);
        if (($data['purpose'] ?? null) === 'register' && $data['channel'] === 'email'
            && CandidateAccount::query()->where('email', strtolower(trim($data['destination'])))->exists()) {
            return ApiResponse::error('A profile with this email already exists. Please sign in.', 422, ['destination' => ['A profile with this email already exists. Please sign in.']]);
        }
        $devCode = $auth->sendCode($data['channel'], $data['destination']);

        return ApiResponse::data(['sent' => true, ...($devCode !== null ? ['dev_code' => $devCode] : [])]);
    }

    public function verifyCode(Request $request, CandidateAuthService $auth): JsonResponse
    {
        $data = $request->validate([
            'channel' => ['required', Rule::in(['email', 'phone'])],
            'destination' => ['required', 'string', 'max:160'],
            'code' => ['required', 'digits:6'],
            'full_name' => ['nullable', 'string', 'max:160'],
        ]);

        return $this->session($auth->verifyCode($data['channel'], $data['destination'], $data['code'], $data['full_name'] ?? null));
    }

    public function register(Request $request, CandidateAuthService $auth): JsonResponse
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email', 'max:160'],
            'password' => ['required', 'string', Password::min(8)->letters()->numbers()],
            'phone' => ['required', 'string', 'max:20'],
            'work_status' => ['required', Rule::in(['fresher', 'experienced'])],
            'marketing_opt_in' => ['nullable', 'boolean'],
            'terms' => ['required', 'accepted'],
            'code' => ['required', 'digits:6'],
        ]);

        return $this->session($auth->register($data), 201);
    }

    public function passwordLogin(Request $request, CandidateAuthService $auth): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:160'],
            'password' => ['required', 'string', 'max:200'],
        ]);

        return $this->session($auth->passwordLogin($data['email'], $data['password']));
    }

    public function me(Request $request): JsonResponse
    {
        return ApiResponse::data($this->profile($this->account($request)));
    }

    public function update(Request $request): JsonResponse
    {
        $account = $this->account($request);
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:160'],
            'headline' => ['nullable', 'string', 'max:160'],
            'work_status' => ['nullable', Rule::in(['fresher', 'experienced'])],
            'location' => ['nullable', 'string', 'max:120'],
            'experience_years' => ['nullable', 'integer', 'between:0,60'],
            'current_company' => ['nullable', 'string', 'max:160'],
            'current_designation' => ['nullable', 'string', 'max:160'],
            'current_salary' => ['nullable', 'string', 'max:80'],
            'expected_salary' => ['nullable', 'string', 'max:80'],
            'notice_period' => ['nullable', 'string', 'max:80'],
            'qualification' => ['nullable', 'string', 'max:160'],
            'skills' => ['nullable', 'string', 'max:1000'],
        ]);
        $account->fill($data)->save();

        return ApiResponse::data($this->profile($account));
    }

    public function applications(Request $request): JsonResponse
    {
        $account = $this->account($request);
        $candidates = Candidate::query()->withoutGlobalScopes()->where('candidate_account_id', $account->id)->pluck('id');
        $rows = JobApplication::query()->withoutGlobalScopes()->whereIn('candidate_id', $candidates)->orderByDesc('id')->get()
            ->map(function (JobApplication $application): array {
                $opening = JobOpening::query()->withoutGlobalScopes()->find($application->job_opening_id);

                return [
                    'id' => $application->id,
                    'job_title' => $opening?->title,
                    'job_slug' => $opening?->is_public ? $opening->slug : null,
                    'company' => $opening ? Organization::query()->whereKey($opening->organization_id)->value('name') : null,
                    'stage' => $application->stage,
                    'applied_on' => $application->created_at?->toDateString(),
                ];
            })->all();

        return ApiResponse::data($rows);
    }

    public function logout(Request $request, CandidateAuthService $auth): JsonResponse
    {
        $auth->signOut((string) $request->bearerToken());

        return ApiResponse::data(['signed_out' => true]);
    }

    /**
     * @param  array{token: string, account: CandidateAccount}  $session
     */
    private function session(array $session, int $status = 200): JsonResponse
    {
        return ApiResponse::data(['token' => $session['token'], 'account' => $this->profile($session['account'])], $status);
    }

    private function account(Request $request): CandidateAccount
    {
        return $request->attributes->get('candidate_account');
    }

    /**
     * @return array<string, mixed>
     */
    private function profile(CandidateAccount $account): array
    {
        return [
            ...$account->only(['id', 'full_name', 'email', 'phone', 'avatar_url', 'headline', 'work_status', 'location', 'experience_years', 'current_company', 'current_designation', 'current_salary', 'expected_salary', 'notice_period', 'qualification', 'skills']),
            'email_verified' => $account->email_verified_at !== null,
            'phone_verified' => $account->phone_verified_at !== null,
            'has_password' => $account->password !== null,
            'linked' => ['google' => $account->google_id !== null, 'linkedin' => $account->linkedin_id !== null],
        ];
    }
}
