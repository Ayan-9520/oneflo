<?php

namespace App\Modules\Recruitment\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\CandidateAccount;
use App\Models\JobApplication;
use App\Models\IssuedDocument;
use App\Models\JobOffer;
use App\Models\JobOpening;
use App\Models\Organization;
use App\Modules\Recruitment\Services\CandidateAuthService;
use App\Modules\Recruitment\Services\OnboardingService;
use App\Modules\Recruitment\Services\RecruitmentService;
use Symfony\Component\HttpFoundation\Response;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
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
        $account = $this->account($request);
        $this->ensureSlug($account);

        return ApiResponse::data($this->profile($account));
    }

    public function publicProfile(string $slug): JsonResponse
    {
        $account = CandidateAccount::query()->where('public_slug', $slug)->where('is_public', true)->firstOrFail();

        return ApiResponse::data([
            'full_name' => $account->full_name,
            'avatar_url' => $account->avatar_url,
            'headline' => $account->headline,
            'location' => $account->location,
            'experience_years' => $account->experience_years,
            'current_company' => $account->current_company,
            'current_designation' => $account->current_designation,
            'qualification' => $account->qualification,
            'skills' => $account->skills,
            'work_status' => $account->work_status,
        ]);
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
            'is_public' => ['sometimes', 'boolean'],
        ]);
        $account->fill($data)->save();
        $this->ensureSlug($account);

        return ApiResponse::data($this->profile($account));
    }

    public function photo(Request $request): JsonResponse
    {
        $account = $this->account($request);
        $data = $request->validate([
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);
        $path = $data['photo']->store('avatars', 'public');
        if (is_string($account->avatar_url) && str_starts_with($account->avatar_url, '/storage/avatars/')) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete(substr($account->avatar_url, strlen('/storage/')));
        }
        $account->avatar_url = '/storage/'.$path;
        $account->save();

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

    public function offers(Request $request): JsonResponse
    {
        $rows = $this->offerQuery($request)->whereIn('status', ['sent', 'accepted', 'declined'])->orderByDesc('id')->get()
            ->map(function (JobOffer $offer): array {
                $application = JobApplication::query()->withoutGlobalScopes()->find($offer->job_application_id);
                $opening = $application ? JobOpening::query()->withoutGlobalScopes()->find($application->job_opening_id) : null;

                return [
                    'id' => $offer->id,
                    'designation' => $offer->designation,
                    'compensation_note' => $offer->compensation_note,
                    'status' => $offer->status,
                    'job_title' => $opening?->title,
                    'company' => Organization::query()->whereKey($offer->organization_id)->value('name'),
                    'sent_on' => $offer->sent_at?->toDateString(),
                    'answered_on' => ($offer->accepted_at ?? $offer->declined_at)?->toDateString(),
                ];
            })->all();

        return ApiResponse::data($rows);
    }

    public function offerLetter(Request $request, int $offer, RecruitmentService $recruitment): Response
    {
        $model = $this->offerQuery($request)->whereIn('status', ['sent', 'accepted', 'declined'])->findOrFail($offer);

        return response($recruitment->offerLetter($model), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="offer-letter-'.$model->id.'.pdf"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function respondOffer(Request $request, int $offer, string $decision, RecruitmentService $recruitment): JsonResponse
    {
        abort_unless(in_array($decision, ['accept', 'decline'], true), 404);
        $model = $this->offerQuery($request)->findOrFail($offer);
        $updated = $recruitment->respondAsCandidate($model, $decision === 'accept');

        return ApiResponse::data(['id' => $updated->id, 'status' => $updated->status]);
    }

    public function letters(Request $request): JsonResponse
    {
        $labels = ['offer_letter' => 'Offer letter', 'appointment_letter' => 'Appointment letter', 'joining_letter' => 'Joining letter'];
        $rows = $this->letterQuery($request)->orderByDesc('id')->get()->map(fn (IssuedDocument $document) => [
            'id' => $document->id,
            'type' => $document->document_type,
            'label' => $labels[$document->document_type] ?? $document->document_type,
            'number' => $document->number,
            'company' => Organization::query()->whereKey($document->organization_id)->value('name'),
            'issued_on' => $document->issued_on instanceof \DateTimeInterface ? $document->issued_on->format('Y-m-d') : (string) $document->issued_on,
        ])->all();

        return ApiResponse::data($rows);
    }

    public function letterPdf(Request $request, int $document, OnboardingService $onboarding): Response
    {
        $model = $this->letterQuery($request)->findOrFail($document);

        return response($onboarding->pdf($model), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$model->number.'.pdf"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<IssuedDocument>
     */
    private function letterQuery(Request $request)
    {
        $candidates = Candidate::query()->withoutGlobalScopes()->where('candidate_account_id', $this->account($request)->id)->pluck('id');

        return IssuedDocument::query()->withoutGlobalScopes()->whereIn('candidate_id', $candidates)
            ->whereIn('document_type', ['offer_letter', 'appointment_letter', 'joining_letter'])->whereNull('voided_at');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<JobOffer>
     */
    private function offerQuery(Request $request)
    {
        $candidates = Candidate::query()->withoutGlobalScopes()->where('candidate_account_id', $this->account($request)->id)->pluck('id');
        $applications = JobApplication::query()->withoutGlobalScopes()->whereIn('candidate_id', $candidates)->pluck('id');

        return JobOffer::query()->withoutGlobalScopes()->whereIn('job_application_id', $applications);
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

    private function ensureSlug(CandidateAccount $account): void
    {
        if (is_string($account->public_slug) && $account->public_slug !== '') {
            return;
        }
        $base = Str::slug((string) $account->full_name) ?: 'profile';
        $base = Str::limit($base, 48, '');
        $slug = $base;
        $n = 2;
        while (CandidateAccount::query()->where('public_slug', $slug)->exists()) {
            $slug = $base.'-'.$n;
            $n++;
        }
        $account->public_slug = $slug;
        $account->save();
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
            ...$account->only(['id', 'full_name', 'email', 'phone', 'avatar_url', 'headline', 'work_status', 'location', 'experience_years', 'current_company', 'current_designation', 'current_salary', 'expected_salary', 'notice_period', 'qualification', 'skills', 'public_slug', 'is_public']),
            'email_verified' => $account->email_verified_at !== null,
            'phone_verified' => $account->phone_verified_at !== null,
            'has_password' => $account->password !== null,
            'linked' => ['google' => $account->google_id !== null, 'linkedin' => $account->linkedin_id !== null],
        ];
    }
}
