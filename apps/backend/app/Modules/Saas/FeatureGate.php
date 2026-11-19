<?php

declare(strict_types=1);

namespace App\Modules\Saas;

use App\Models\Employee;
use App\Models\Organization;
use App\Models\Subscription;

final class FeatureGate
{
    public function assert(Organization $organization, string $feature): void
    {
        $subscription = $this->subscription($organization);
        if ($subscription === null) {
            return;
        }
        $this->assertActive($subscription);
        $features = $subscription->plan?->features ?? [];
        if (! in_array($feature, $features, true)) {
            abort(403, 'This plan does not include that feature.');
        }
    }

    public function assertCanAddEmployee(Organization $organization): void
    {
        $subscription = $this->subscription($organization);
        if ($subscription === null) {
            return;
        }
        $this->assertActive($subscription);
        $limit = $subscription->plan?->employee_limit;
        if ($limit !== null && Employee::withoutGlobalScopes()->where('organization_id', $organization->id)->count() >= $limit) {
            abort(422, 'This plan has reached its employee limit.');
        }
    }

    private function subscription(Organization $organization): ?Subscription
    {
        return Subscription::withoutGlobalScopes()->with('plan')->where('organization_id', $organization->id)->first();
    }

    private function assertActive(Subscription $subscription): void
    {
        $expired = $subscription->status === 'expired'
            || $subscription->status === 'cancelled'
            || ($subscription->status === 'trial' && $subscription->trial_ends_on !== null && $subscription->trial_ends_on->isPast())
            || ($subscription->status === 'active' && $subscription->current_period_end->isPast());
        if ($expired && $subscription->status !== 'expired' && $subscription->status !== 'cancelled') {
            $subscription->status = 'expired';
            $subscription->save();
        }
        if ($expired || $subscription->status === 'expired' || $subscription->status === 'cancelled') {
            abort(403, 'The subscription is not active.');
        }
    }
}
