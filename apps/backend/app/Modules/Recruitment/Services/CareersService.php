<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Services;

use App\Models\Brand;
use App\Models\BrandIdentity;
use App\Models\Candidate;
use App\Models\CandidateAccount;
use App\Models\CompanyProfile;
use App\Models\Department;
use App\Models\Designation;
use App\Models\CandidateResume;
use App\Models\Employee;
use App\Models\EmployeeReferral;
use App\Models\JobApplication;
use App\Models\JobOpening;
use App\Models\JobRequisition;
use App\Models\Organization;
use App\Models\ReferralReward;
use App\Models\ReferralRewardPolicy;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\OrganizationContext;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class CareersService
{
    public const PRIVACY_VERSION = '2026-11';

    /** @var list<string> */
    public const CATEGORIES = ['Sales & Telecalling', 'Management', 'Insurance', 'Loans & Finance', 'Automotive', 'Digital Marketing', 'Marketing', 'Operations', 'Customer Support', 'Travel & Tourism', 'Technology & IT', 'Human Resources', 'Business Development', 'Other'];

    /** @var list<string> */
    public const SOURCES = ['OneFlo Careers Website', 'Facebook', 'LinkedIn', 'WhatsApp', 'Instagram', 'Employee Referral', 'Internal HR', 'Walk-in', 'Email', 'Recruitment Agency', 'External Job Portal', 'Other', 'Unknown'];

    public function __construct(
        private readonly OrganizationContext $context,
        private readonly AuditLogger $audit,
        private readonly RecruitmentService $recruitment,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function companies(): array
    {
        $counts = $this->publicOpenings()->selectRaw('organization_id, count(*) as total')->groupBy('organization_id')->pluck('total', 'organization_id');

        return Organization::query()->where('status', 'active')->whereNotNull('slug')->orderBy('name')->get()
            ->map(fn (Organization $organization) => [...$this->company($organization), 'jobs' => (int) ($counts[$organization->id] ?? 0)])
            ->filter(fn (array $company) => $company['jobs'] > 0 || $company['has_brand'])
            ->map(fn (array $company) => collect($company)->except('has_brand')->all())
            ->values()
            ->all();
    }

    /**
     * @return list<array{name: string, jobs: int}>
     */
    public function categories(): array
    {
        $counts = $this->publicOpenings()->selectRaw('category, count(*) as total')->groupBy('category')->pluck('total', 'category');

        return array_map(fn (string $name) => ['name' => $name, 'jobs' => (int) ($counts[$name] ?? 0)], self::CATEGORIES);
    }

    /**
     * @return array{locations: list<string>, departments: list<string>, designations: list<string>}
     */
    public function facets(): array
    {
        $openings = $this->publicOpenings()->with(['requisition' => fn ($query) => $query->withoutGlobalScopes()])->get();
        $departmentIds = $openings->pluck('requisition.department_id')->filter()->unique()->all();
        $designationIds = $openings->pluck('requisition.designation_id')->filter()->unique()->all();

        return [
            'locations' => $openings->pluck('location')->filter()->unique()->sort()->values()->all(),
            'departments' => Department::query()->withoutGlobalScopes()->whereIn('id', $departmentIds ?: [-1])->orderBy('name')->pluck('name')->unique()->values()->all(),
            'designations' => Designation::query()->withoutGlobalScopes()->whereIn('id', $designationIds ?: [-1])->orderBy('name')->pluck('name')->unique()->values()->all(),
        ];
    }

    /**
     * Active departments and designations per business. A role links to a job only when a published opening exists.
     *
     * @return list<array{slug: string, name: string, logo_url: string|null, departments: list<array{name: string, roles: list<array{name: string, job_slug: string|null}>}>}>
     */
    public function teams(): array
    {
        $openings = $this->publicOpenings()->with(['requisition' => fn ($query) => $query->withoutGlobalScopes()])->orderByDesc('published_at')->get();
        $teams = [];
        foreach (Organization::query()->where('status', 'active')->whereNotNull('slug')->orderBy('name')->get() as $organization) {
            $designations = Designation::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->where('status', 'active')->whereNotNull('department_id')->orderBy('name')->get()->groupBy('department_id');
            if ($designations->isEmpty()) {
                continue;
            }
            $jobs = $openings->where('organization_id', $organization->id);
            $departments = Department::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->where('status', 'active')->whereIn('id', $designations->keys())->orderBy('name')->get()
                ->map(fn (Department $department) => [
                    'name' => $department->name,
                    'roles' => $designations[$department->id]->map(function (Designation $designation) use ($jobs): array {
                        $job = $jobs->first(fn (JobOpening $opening) => $opening->requisition?->designation_id === $designation->id || strcasecmp(trim((string) $opening->title), $designation->name) === 0);

                        return ['name' => $designation->name, 'job_slug' => $job?->slug];
                    })->values()->all(),
                ])->values()->all();
            $company = $this->company($organization);
            $teams[] = ['slug' => (string) $organization->slug, 'name' => $organization->name, 'logo_url' => $company['logo_url'], 'departments' => $departments];
        }

        return $teams;
    }

    public function logo(string $slug): ?string
    {
        $organization = Organization::query()->where('slug', $slug)->where('status', 'active')->first();
        if ($organization === null) {
            return null;
        }
        $brand = Brand::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->orderByDesc('is_primary')->first();
        if ($brand === null) {
            return null;
        }
        $identity = BrandIdentity::query()->withoutGlobalScopes()->where('brand_id', $brand->id)->first();

        return $identity?->logo_path ?: $identity?->light_logo_path ?: $brand->logo_path;
    }

    private function logoVersion(string $path): int
    {
        $disk = \Illuminate\Support\Facades\Storage::disk('documents');

        return $disk->exists($path) ? $disk->lastModified($path) : 0;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{data: list<array<string, mixed>>, page: int, last_page: int}
     */
    public function jobs(array $filters): array
    {
        $query = $this->publicOpenings()->with(['organization']);
        if (! empty($filters['company'])) {
            $organization = Organization::query()->where('slug', $filters['company'])->first();
            $query->where('organization_id', $organization?->id ?? 0);
        }
        foreach (['category', 'employment_type'] as $field) {
            if (! empty($filters[$field]) && is_string($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }
        if (! empty($filters['location']) && is_string($filters['location'])) {
            $query->where('location', 'like', '%'.$filters['location'].'%');
        }
        foreach (['department' => [Department::class, 'department_id'], 'designation' => [Designation::class, 'designation_id']] as $key => [$model, $column]) {
            if (! empty($filters[$key]) && is_string($filters[$key])) {
                $ids = $model::query()->withoutGlobalScopes()->where('name', $filters[$key])->pluck('id')->all();
                $query->whereHas('requisition', fn ($inner) => $inner->withoutGlobalScopes()->whereIn($column, $ids ?: [-1]));
            }
        }
        if (! empty($filters['q'])) {
            $term = '%'.$filters['q'].'%';
            $query->where(function ($inner) use ($term): void {
                $inner->where('title', 'like', $term)->orWhere('description', 'like', $term);
            });
        }
        if (isset($filters['experience']) && $filters['experience'] !== '') {
            $years = (int) $filters['experience'];
            $query->where(function ($inner) use ($years): void {
                $inner->whereNull('experience_min')->orWhere('experience_min', '<=', $years);
            });
        }
        $page = max(1, (int) ($filters['page'] ?? 1));
        $total = (clone $query)->count();
        $rows = $query->orderByDesc('published_at')->forPage($page, 12)->get();

        return [
            'data' => $rows->map(fn (JobOpening $opening) => $this->card($opening))->all(),
            'page' => $page,
            'last_page' => max(1, (int) ceil($total / 12)),
            'total' => $total,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function job(string $slug): array
    {
        $opening = $this->publicOpenings()->where('slug', $slug)->first();
        if ($opening === null) {
            abort(404, 'The requested resource was not found.');
        }

        return $this->detail($opening);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{application_id: int, acknowledgement: string}
     */
    public function apply(string $slug, array $input, UploadedFile $resume, ?CandidateAccount $account = null): array
    {
        $opening = $this->publicOpenings()->where('slug', $slug)->first();
        if ($opening === null || ($opening->deadline_on !== null && $opening->deadline_on->isPast())) {
            throw ValidationException::withMessages(['job' => ['This opening is not accepting applications.']]);
        }
        if (empty($input['consent'])) {
            throw ValidationException::withMessages(['consent' => ['Consent is required before an application can be saved.']]);
        }
        $organization = Organization::query()->find($opening->organization_id);
        $this->context->set($organization);
        $source = in_array($input['source'] ?? '', self::SOURCES, true) ? $input['source'] : 'OneFlo Careers Website';
        $referral = $this->referralFor($opening, $input['ref'] ?? null);
        if ($referral !== null) {
            $source = 'Employee Referral';
        }

        return DB::transaction(function () use ($opening, $input, $resume, $source, $referral, $organization, $account): array {
            $candidate = $account ? $this->accountCandidate($account, $input) : $this->matchCandidate($input);
            $parts = preg_split('/\s+/', trim((string) $input['full_name'])) ?: [];
            $family = count($parts) > 1 ? (string) array_pop($parts) : $parts[0];
            $given = $parts === [] ? $family : implode(' ', $parts);
            if ($candidate === null) {
                $candidate = Candidate::query()->create([
                    'given_name' => $given,
                    'family_name' => $family,
                    'email' => strtolower(trim((string) $input['email'])),
                    'phone' => $this->phone((string) $input['phone']),
                    'source' => $source,
                    'status' => 'active',
                    'location' => $input['location'],
                    'experience_years' => $input['experience_years'],
                    'current_company' => $input['current_company'] ?? null,
                    'current_designation' => $input['current_designation'] ?? null,
                    'notice_period' => $input['notice_period'],
                    'qualification' => $input['qualification'],
                    'skills' => $input['skills'],
                ]);
            }
            if ($account !== null && $candidate->candidate_account_id === null) {
                $candidate->candidate_account_id = $account->id;
                $candidate->save();
            }
            if (JobApplication::query()->where('job_opening_id', $opening->id)->where('candidate_id', $candidate->id)->exists()) {
                throw ValidationException::withMessages(['email' => ['An application for this opening is already on file.']]);
            }
            try {
                $application = JobApplication::query()->create([
                    'job_opening_id' => $opening->id,
                    'candidate_id' => $candidate->id,
                    'stage' => 'applied',
                    'source' => $source,
                    'utm_source' => $input['utm_source'] ?? null,
                    'utm_medium' => $input['utm_medium'] ?? null,
                    'utm_campaign' => $input['utm_campaign'] ?? null,
                    'utm_content' => $input['utm_content'] ?? null,
                    'message' => $input['message'] ?? null,
                    'current_salary' => $input['current_salary'] ?? null,
                    'expected_salary' => $input['expected_salary'] ?? null,
                    'consented_at' => now(),
                    'privacy_version' => self::PRIVACY_VERSION,
                    'acknowledgement_status' => 'not_sent',
                ]);
            } catch (QueryException) {
                throw ValidationException::withMessages(['email' => ['An application for this opening is already on file.']]);
            }
            $this->recruitment->storeResume($candidate, $resume);
            if ($referral !== null && $referral->job_application_id === null) {
                $referral->candidate_id = $candidate->id;
                $referral->job_application_id = $application->id;
                $referral->status = 'application_received';
                $referral->save();
                $this->trackReward($referral);
            }
            $status = $this->acknowledge($organization, $candidate, $opening, $application);
            $application->acknowledgement_status = $status;
            $application->save();

            return ['application_id' => $application->id, 'acknowledgement' => $status];
        });
    }

    public function publish(JobOpening $opening, array $input, User $actor): JobOpening
    {
        $requisition = JobRequisition::query()->find($opening->job_requisition_id);
        if ($requisition === null || $requisition->status !== 'approved' || ! in_array($opening->status, ['open'], true)) {
            throw ValidationException::withMessages(['status' => ['Only an open opening from an approved requisition can be published.']]);
        }
        $opening->fill([
            'is_public' => true,
            'location' => $input['location'],
            'employment_type' => $input['employment_type'],
            'category' => $input['category'],
            'experience_min' => $input['experience_min'] ?? null,
            'experience_max' => $input['experience_max'] ?? null,
            'salary_public' => (bool) ($input['salary_public'] ?? false),
            'salary_note' => ($input['salary_public'] ?? false) ? ($input['salary_note'] ?? null) : null,
            'show_headcount' => (bool) ($input['show_headcount'] ?? false),
            'responsibilities' => $input['responsibilities'] ?? null,
            'qualifications' => $input['qualifications'] ?? null,
            'skills' => $input['skills'] ?? null,
            'benefits' => $input['benefits'] ?? null,
            'deadline_on' => $input['deadline_on'] ?? null,
            'published_at' => $opening->published_at ?? now(),
        ]);
        if (! empty($input['description'])) {
            $opening->description = $input['description'];
        }
        if ($opening->slug === null) {
            $opening->slug = Str::slug($opening->title).'-'.$opening->id;
        }
        $opening->save();
        $this->audit->accessChange($actor, $this->context->get(), 'opening.published', ['opening_id' => $opening->id]);

        return $opening;
    }

    public function close(JobOpening $opening, User $actor, string $status): JobOpening
    {
        $opening->status = $status;
        $opening->is_public = false;
        $opening->save();
        $this->audit->accessChange($actor, $this->context->get(), 'opening.'.$status, ['opening_id' => $opening->id]);

        return $opening;
    }

    public function unpublish(JobOpening $opening, User $actor): JobOpening
    {
        $opening->is_public = false;
        $opening->save();
        $this->audit->accessChange($actor, $this->context->get(), 'opening.unpublished', ['opening_id' => $opening->id]);

        return $opening;
    }

    public function referralLink(Employee $employee, JobOpening $opening): EmployeeReferral
    {
        if (! $opening->is_public || $opening->status !== 'open' || $opening->organization_id !== $employee->organization_id) {
            throw ValidationException::withMessages(['job_opening_id' => ['This opening is not available for referral.']]);
        }
        $existing = EmployeeReferral::query()->where('employee_id', $employee->id)->where('job_opening_id', $opening->id)->first();
        if ($existing !== null) {
            return $existing;
        }

        return EmployeeReferral::query()->create([
            'employee_id' => $employee->id,
            'job_opening_id' => $opening->id,
            'token' => Str::random(48),
            'status' => 'submitted',
            'expires_at' => now()->addDays(90),
        ]);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function savePolicy(array $input, User $actor): ReferralRewardPolicy
    {
        $policy = ReferralRewardPolicy::query()->updateOrCreate(
            ['job_opening_id' => $input['job_opening_id'] ?? null],
            [
                'enabled' => (bool) $input['enabled'],
                'amount' => (int) $input['amount'],
                'milestone' => $input['milestone'],
            ],
        );
        $this->audit->accessChange($actor, $this->context->get(), 'referral_reward.configured', ['policy_id' => $policy->id]);

        return $policy;
    }

    public function decideReward(ReferralReward $reward, User $actor, string $status, ?string $notes): ReferralReward
    {
        $reward->load('referral.employee');
        $referral = EmployeeReferral::query()->find($reward->employee_referral_id);
        $employee = $referral ? Employee::query()->find($referral->employee_id) : null;
        if ($employee !== null && $employee->user_id === $actor->id) {
            throw ValidationException::withMessages(['status' => ['You cannot approve your own referral reward.']]);
        }
        if (! in_array($status, ['eligible', 'approved', 'rejected'], true)) {
            throw ValidationException::withMessages(['status' => ['Choose eligible, approved, or rejected.']]);
        }
        $reward->status = $status;
        $reward->notes = $notes;
        if ($status === 'approved') {
            $reward->approved_by = $actor->id;
            $reward->approved_at = now();
        }
        $reward->save();
        $this->audit->accessChange($actor, $this->context->get(), 'referral_reward.decided', ['reward_id' => $reward->id, 'status' => $status]);

        return $reward;
    }

    private function publicOpenings()
    {
        return JobOpening::query()->withoutGlobalScopes()->where('is_public', true)->where('status', 'open')->whereHas('requisition', function ($query): void {
            $query->withoutGlobalScopes()->where('status', 'approved');
        })->where(function ($query): void {
            $query->whereNull('deadline_on')->orWhereDate('deadline_on', '>=', now()->toDateString());
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function company(Organization $organization): array
    {
        $brand = Brand::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->orderByDesc('is_primary')->first();
        $profile = CompanyProfile::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->orderBy('id')->first();

        return [
            'name' => $brand?->name ?: $organization->name,
            'slug' => $organization->slug,
            'description' => null,
            'industry' => null,
            'logo_url' => ($logo = $this->logo((string) $organization->slug)) ? '/api/v1/careers/companies/'.$organization->slug.'/logo?v='.substr(md5($logo.'|'.$this->logoVersion($logo)), 0, 8) : null,
            'website' => $profile?->website,
            'contact_email' => $profile?->contact_email,
            'contact_phone' => $profile?->contact_phone,
            'has_brand' => $brand !== null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function card(JobOpening $opening): array
    {
        $organization = Organization::query()->find($opening->organization_id);
        $company = $organization ? $this->company($organization) : ['name' => '', 'slug' => ''];
        $requisition = JobRequisition::query()->withoutGlobalScopes()->find($opening->job_requisition_id);

        return [
            'slug' => $opening->slug,
            'title' => $opening->title,
            'company' => $company['name'],
            'company_slug' => $company['slug'],
            'company_logo' => $company['logo_url'] ?? null,
            'department' => $requisition?->department_id ? Department::query()->withoutGlobalScopes()->whereKey($requisition->department_id)->value('name') : null,
            'designation' => $requisition?->designation_id ? Designation::query()->withoutGlobalScopes()->whereKey($requisition->designation_id)->value('name') : null,
            'location' => $opening->location,
            'employment_type' => $opening->employment_type,
            'category' => $opening->category,
            'experience_min' => $opening->experience_min,
            'experience_max' => $opening->experience_max,
            'salary_note' => $opening->salary_public ? $opening->salary_note : null,
            'headcount' => $opening->show_headcount ? $requisition?->headcount : null,
            'posted_on' => $opening->published_at?->toDateString(),
            'deadline_on' => $opening->deadline_on?->toDateString(),
            'summary' => $opening->description ? \Illuminate\Support\Str::limit(trim((string) preg_replace('/\s+/', ' ', strip_tags($opening->description))), 160) : null,
            'skills' => $opening->skills,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detail(JobOpening $opening): array
    {
        return [
            ...$this->card($opening),
            'description' => $opening->description,
            'responsibilities' => $opening->responsibilities,
            'qualifications' => $opening->qualifications,
            'skills' => $opening->skills,
            'benefits' => $opening->benefits,
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function matchCandidate(array $input): ?Candidate
    {
        $email = strtolower(trim((string) $input['email']));
        $phone = $this->phone((string) $input['phone']);
        $byEmail = Candidate::query()->whereRaw('lower(email) = ?', [$email])->first();
        if ($byEmail !== null) {
            return $byEmail;
        }

        return $phone === '' ? null : Candidate::query()->where('phone', $phone)->first();
    }

    /**
     * A signed-in candidate only takes over guest records that carry an
     * email or mobile number the account has verified.
     */
    private function accountCandidate(CandidateAccount $account, array $input): ?Candidate
    {
        $own = Candidate::query()->where('candidate_account_id', $account->id)->first();
        if ($own !== null) {
            return $own;
        }
        $match = $this->matchCandidate($input);
        if ($match === null) {
            return null;
        }
        $verifiedEmail = $account->email_verified_at !== null && strtolower((string) $match->email) === $account->email;
        $verifiedPhone = $account->phone_verified_at !== null && $match->phone === $account->phone;
        if ($match->candidate_account_id === null && ($verifiedEmail || $verifiedPhone)) {
            return $match;
        }
        throw ValidationException::withMessages(['email' => ['Use the email or mobile number verified on your profile, or sign out to apply as a guest.']]);
    }

    private function phone(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?? '';
    }

    private function referralFor(JobOpening $opening, mixed $token): ?EmployeeReferral
    {
        if (! is_string($token) || $token === '') {
            return null;
        }
        $referral = EmployeeReferral::query()->withoutGlobalScopes()->where('token', $token)->where('job_opening_id', $opening->id)->first();
        if ($referral === null || ($referral->expires_at !== null && $referral->expires_at->isPast())) {
            throw ValidationException::withMessages(['ref' => ['This referral link is not valid for this opening.']]);
        }

        return $referral;
    }

    private function trackReward(EmployeeReferral $referral): void
    {
        $policy = ReferralRewardPolicy::query()->where('enabled', true)->where(function ($query) use ($referral): void {
            $query->where('job_opening_id', $referral->job_opening_id)->orWhereNull('job_opening_id');
        })->orderByDesc('job_opening_id')->first();
        ReferralReward::query()->firstOrCreate(
            ['employee_referral_id' => $referral->id],
            ['amount' => $policy?->amount ?? 0, 'status' => $policy ? 'pending' : 'not_eligible', 'payout_status' => 'unpaid'],
        );
    }

    private function acknowledge(Organization $organization, Candidate $candidate, JobOpening $opening, JobApplication $application): string
    {
        if (! in_array(config('mail.default'), ['smtp', 'ses', 'postmark', 'resend'], true) || $candidate->email === null) {
            return 'not_sent';
        }
        try {
            Mail::raw(
                $candidate->fullName().", we received your application for {$opening->title} at {$organization->name}. Reference {$application->id}. A recruiter will contact you if your profile is shortlisted.",
                function ($message) use ($candidate, $opening): void {
                    $message->to($candidate->email)->subject('Application received: '.$opening->title);
                },
            );

            return 'sent';
        } catch (\Throwable) {
            return 'pending';
        }
    }
}
