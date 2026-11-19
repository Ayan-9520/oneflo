<?php

declare(strict_types=1);

namespace App\Modules\Saas;

use App\Models\BillingInvoice;
use App\Models\Branch;
use App\Models\CompanyProfile;
use App\Models\Employee;
use App\Models\EmploymentRecord;
use App\Models\IssuedDocument;
use App\Models\JobOpening;
use App\Models\LegalEntity;
use App\Models\Organization;
use App\Models\OrganizationAccess;
use App\Models\OrganizationRegistration;
use App\Models\PayrollRun;
use App\Models\Platform;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\OrganizationContext;
use App\Support\PlanFeatures;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class SaasService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function register(User $user, string $name, ?string $slug): OrganizationRegistration
    {
        $slug = $slug !== null && $slug !== '' ? Str::slug($slug) : Str::slug($name);
        if ($slug === '' || Organization::query()->where('slug', $slug)->exists()) {
            throw ValidationException::withMessages(['slug' => ['Choose a different organization address.']]);
        }

        return DB::transaction(function () use ($user, $name, $slug): OrganizationRegistration {
            $organization = Organization::query()->create([
                'platform_id' => $this->platformId(),
                'name' => $name,
                'slug' => $slug,
                'status' => 'pending',
            ]);
            app(OrganizationContext::class)->set($organization);
            $registration = new OrganizationRegistration;
            $registration->organization_id = $organization->id;
            $registration->user_id = $user->id;
            $registration->status = 'pending';
            $registration->save();

            return $registration->load('organization');
        });
    }

    public function approve(OrganizationRegistration $registration, SubscriptionPlan $plan, User $actor): Subscription
    {
        if ($registration->status !== 'pending') {
            throw ValidationException::withMessages(['status' => ['This registration is already reviewed.']]);
        }
        if ($plan->status !== 'active') {
            throw ValidationException::withMessages(['plan_id' => ['Choose an active plan.']]);
        }
        $organization = Organization::query()->findOrFail($registration->organization_id);

        return DB::transaction(function () use ($registration, $plan, $actor, $organization): Subscription {
            app(OrganizationContext::class)->set($organization);
            $organization->status = 'active';
            $organization->save();
            $subscription = $this->startSubscription($organization, $plan);
            $roleId = Role::query()->where('slug', 'business-admin')->value('id');
            OrganizationAccess::query()->create([
                'organization_id' => $organization->id,
                'user_id' => $registration->user_id,
                'role_id' => $roleId,
            ]);
            $registration->status = 'approved';
            $registration->reviewed_by = $actor->id;
            $registration->reviewed_at = now();
            $registration->save();
            $this->audit->crossOrganization($actor, $organization, 'organization.approved', $organization, [
                'plan_id' => $plan->id,
            ]);

            return $subscription;
        });
    }

    public function reject(OrganizationRegistration $registration, User $actor, ?string $note): void
    {
        if ($registration->status !== 'pending') {
            throw ValidationException::withMessages(['status' => ['This registration is already reviewed.']]);
        }
        $registration->status = 'rejected';
        $registration->reviewed_by = $actor->id;
        $registration->reviewed_at = now();
        $registration->note = $note;
        $registration->save();
        $organization = Organization::query()->find($registration->organization_id);
        if ($organization !== null) {
            $this->audit->crossOrganization($actor, $organization, 'organization.rejected', $organization);
        }
    }

    public function setActivation(Organization $organization, string $status, User $actor): Organization
    {
        if (! in_array($organization->status, ['active', 'inactive'], true) || ! in_array($status, ['active', 'inactive'], true)) {
            throw ValidationException::withMessages(['status' => ['Activation changes an approved organization between active and inactive.']]);
        }
        $organization->status = $status;
        $organization->save();
        $this->audit->crossOrganization($actor, $organization, 'organization.'.$status, $organization);

        return $organization;
    }

    public function renew(Subscription $subscription, User $actor): BillingInvoice
    {
        $plan = $subscription->plan;
        if ($plan === null || $plan->price_paise === null) {
            throw ValidationException::withMessages(['price' => ['Set a plan price before recording a renewal.']]);
        }
        $start = $subscription->current_period_end->isFuture()
            ? $subscription->current_period_end->copy()
            : now()->startOfDay();
        $end = $plan->interval === 'yearly' ? $start->copy()->addYear() : $start->copy()->addMonth();

        return DB::transaction(function () use ($subscription, $plan, $actor, $start, $end): BillingInvoice {
            $invoice = new BillingInvoice;
            $invoice->organization_id = $subscription->organization_id;
            $invoice->subscription_id = $subscription->id;
            $invoice->amount_paise = $plan->price_paise;
            $invoice->currency = $plan->currency;
            $invoice->period_start = $start->toDateString();
            $invoice->period_end = $end->toDateString();
            $invoice->status = 'recorded';
            $invoice->reference = 'renewal';
            $invoice->recorded_by = $actor->id;
            $invoice->save();
            $subscription->status = 'active';
            $subscription->trial_ends_on = null;
            $subscription->current_period_start = $start->toDateString();
            $subscription->current_period_end = $end->toDateString();
            $subscription->save();

            return $invoice;
        });
    }

    /**
     * @return array<string, int|string|null>
     */
    public function usage(Organization $organization): array
    {
        $subscription = Subscription::withoutGlobalScopes()->with('plan')->where('organization_id', $organization->id)->first();

        return [
            'employees' => Employee::query()->count(),
            'employee_limit' => $subscription?->plan?->employee_limit,
            'issued_documents' => IssuedDocument::query()->count(),
            'locked_payroll_runs' => PayrollRun::query()->where('status', 'locked')->count(),
            'open_positions' => JobOpening::query()->where('status', 'published')->count(),
        ];
    }

    /**
     * @return list<array{key: string, label: string, complete: bool}>
     */
    public function onboarding(): array
    {
        return [
            ['key' => 'legal_entity', 'label' => 'Legal entity', 'complete' => LegalEntity::query()->exists()],
            ['key' => 'company_profile', 'label' => 'Employer profile', 'complete' => CompanyProfile::query()->exists()],
            ['key' => 'branch', 'label' => 'Branch', 'complete' => Branch::query()->exists()],
            ['key' => 'employee', 'label' => 'Employee', 'complete' => Employee::query()->exists()],
            ['key' => 'access', 'label' => 'Business access', 'complete' => OrganizationAccess::query()->exists()],
        ];
    }

    /**
     * @return array{exits: int, headcount: int, rate: string|null}
     */
    public function attrition(Organization $organization): array
    {
        $headcount = Employee::query()->where('status', 'active')->count();
        $exits = EmploymentRecord::query()
            ->whereIn('change_type', ['resignation', 'exit'])
            ->whereDate('effective_on', '>=', now()->subYear()->toDateString())
            ->count();

        return [
            'exits' => $exits,
            'headcount' => $headcount,
            'rate' => $headcount === 0 ? null : number_format($exits / $headcount, 4, '.', ''),
        ];
    }

    /**
     * @param  list<string>  $features
     */
    public function assertEmployeeCapacity(): void
    {
        $limit = Subscription::query()->with('plan')->first()?->plan?->employee_limit;
        if ($limit !== null && Employee::query()->count() >= $limit) {
            throw ValidationException::withMessages(['employee_code' => ['The plan employee limit has been reached.']]);
        }
    }

    public function assertFeatures(array $features): void
    {
        $unknown = array_diff($features, PlanFeatures::ALL);
        if ($features === [] || $unknown !== []) {
            throw ValidationException::withMessages(['features' => ['Choose one or more known product features.']]);
        }
    }

    private function startSubscription(Organization $organization, SubscriptionPlan $plan): Subscription
    {
        $trial = $plan->trial_days > 0;
        $end = $trial ? now()->addDays($plan->trial_days) : ($plan->interval === 'yearly' ? now()->addYear() : now()->addMonth());
        $subscription = new Subscription;
        $subscription->organization_id = $organization->id;
        $subscription->plan_id = $plan->id;
        $subscription->status = $trial ? 'trial' : 'active';
        $subscription->trial_ends_on = $trial ? $end->toDateString() : null;
        $subscription->current_period_start = now()->toDateString();
        $subscription->current_period_end = $end->toDateString();
        $subscription->save();

        return $subscription;
    }

    private function platformId(): int
    {
        return (int) Platform::query()->firstOrCreate(
            ['slug' => 'oneflo'],
            ['name' => 'OneFlo'],
        )->id;
    }
}
