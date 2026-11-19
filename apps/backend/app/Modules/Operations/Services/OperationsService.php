<?php

declare(strict_types=1);

namespace App\Modules\Operations\Services;

use App\Models\Asset;
use App\Models\AssetAllocation;
use App\Models\Employee;
use App\Models\ExpenseClaim;
use App\Models\PerformanceReview;
use App\Models\SlaPolicy;
use App\Models\Ticket;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\OrganizationContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class OperationsService
{
    public function __construct(
        private readonly OrganizationContext $context,
        private readonly AuditLogger $audit,
    ) {}

    public function submitSelfReview(PerformanceReview $review, User $actor, int $score, string $comments): PerformanceReview
    {
        $this->assertOwnEmployee($review->employee_id, $actor);
        if ($review->status !== 'draft') {
            throw ValidationException::withMessages(['status' => ['Self-assessment is only open on a draft review.']]);
        }
        $review->self_score = $score;
        $review->self_comments = $comments;
        $review->status = 'self_submitted';
        $review->save();

        return $review;
    }

    public function completeReview(PerformanceReview $review, User $actor, int $score, string $comments, bool $promotion): PerformanceReview
    {
        if ($review->status !== 'self_submitted') {
            throw ValidationException::withMessages(['status' => ['Complete the review after the employee self-assessment.']]);
        }
        $review->manager_score = $score;
        $review->manager_comments = $comments;
        $review->promotion_recommended = $promotion;
        $review->status = 'completed';
        $review->reviewed_by = $actor->id;
        $review->save();
        $this->audit->accessChange($actor, $this->context->get(), 'performance.completed', ['review_id' => $review->id, 'score' => $score]);

        return $review;
    }

    public function assignAsset(Asset $asset, Employee $employee, string $allocatedOn, User $actor): AssetAllocation
    {
        if ($asset->status !== 'in_stock') {
            throw ValidationException::withMessages(['asset' => ['Only an in-stock asset can be allocated.']]);
        }

        return DB::transaction(function () use ($asset, $employee, $allocatedOn, $actor): AssetAllocation {
            $asset->status = 'assigned';
            $asset->save();
            $allocation = AssetAllocation::query()->create([
                'asset_id' => $asset->id,
                'employee_id' => $employee->id,
                'allocated_on' => $allocatedOn,
            ]);
            $this->audit->accessChange($actor, $this->context->get(), 'asset.assigned', ['asset_id' => $asset->id, 'employee_id' => $employee->id]);

            return $allocation;
        });
    }

    public function returnAsset(AssetAllocation $allocation, string $returnedOn, string $condition, User $actor): AssetAllocation
    {
        if ($allocation->returned_on !== null) {
            throw ValidationException::withMessages(['asset' => ['This asset has already been returned.']]);
        }
        $allocation->returned_on = $returnedOn;
        $allocation->return_condition = $condition;
        $allocation->save();
        $asset = $allocation->asset;
        $asset->status = 'in_stock';
        $asset->condition = $condition;
        $asset->save();
        $this->audit->accessChange($actor, $this->context->get(), 'asset.returned', ['asset_id' => $asset->id]);
        $organization = $this->context->get();
        if ($organization !== null) {
            $allocation->loadMissing('employee.user');
            app(\App\Modules\Notifications\NotificationService::class)->publish($organization, [$allocation->employee?->user], \App\Modules\Notifications\NotificationEvents::ASSET_RETURNED, 'asset-return:'.$allocation->id, 'Asset return', ($asset->asset_code ?? 'An asset').' was returned on '.$returnedOn.'.');
        }

        return $allocation;
    }

    public function managerApprove(ExpenseClaim $claim, User $actor): ExpenseClaim
    {
        if ($claim->status !== 'submitted') {
            throw ValidationException::withMessages(['status' => ['The claim is not waiting for the manager.']]);
        }
        $employee = $claim->employee;
        if ($employee->user_id === $actor->id) {
            throw ValidationException::withMessages(['status' => ['You cannot approve your own expense.']]);
        }
        $managerUserId = $employee->manager_id === null ? null : Employee::query()->whereKey($employee->manager_id)->value('user_id');
        if ($managerUserId !== null && (int) $managerUserId !== $actor->id) {
            throw ValidationException::withMessages(['status' => ['Only the reporting manager can approve this claim.']]);
        }
        $claim->status = 'manager_approved';
        $claim->manager_approved_by = $actor->id;
        $claim->save();

        return $claim;
    }

    public function financeApprove(ExpenseClaim $claim, User $actor): ExpenseClaim
    {
        if ($claim->status !== 'manager_approved') {
            throw ValidationException::withMessages(['status' => ['Finance approval follows manager approval.']]);
        }
        if ($claim->manager_approved_by === $actor->id || $claim->employee->user_id === $actor->id) {
            throw ValidationException::withMessages(['status' => ['A different finance or HR approver must complete this claim.']]);
        }
        $claim->status = 'approved';
        $claim->finance_approved_by = $actor->id;
        $claim->save();
        $this->audit->accessChange($actor, $this->context->get(), 'expense.approved', ['claim_id' => $claim->id]);

        return $claim;
    }

    public function reject(ExpenseClaim $claim, User $actor, string $reason): ExpenseClaim
    {
        if (! in_array($claim->status, ['submitted', 'manager_approved'], true)) {
            throw ValidationException::withMessages(['status' => ['This claim can no longer be rejected.']]);
        }
        $claim->status = 'rejected';
        $claim->rejection_reason = $reason;
        $claim->save();

        return $claim;
    }

    public function resubmit(ExpenseClaim $claim, User $actor): ExpenseClaim
    {
        $this->assertOwnEmployee($claim->employee_id, $actor);
        if ($claim->status !== 'rejected') {
            throw ValidationException::withMessages(['status' => ['Only a rejected claim can be resubmitted.']]);
        }
        $claim->status = 'submitted';
        $claim->rejection_reason = null;
        $claim->manager_approved_by = null;
        $claim->save();

        return $claim;
    }

    public function reimburse(ExpenseClaim $claim, User $actor): ExpenseClaim
    {
        if ($claim->status !== 'approved') {
            throw ValidationException::withMessages(['status' => ['Reimburse an approved claim.']]);
        }
        $claim->status = 'reimbursed';
        $claim->save();
        $this->audit->accessChange($actor, $this->context->get(), 'expense.reimbursed', ['claim_id' => $claim->id]);
        $organization = $this->context->get();
        if ($organization !== null) {
            app(\App\Modules\Saas\IntegrationDispatcher::class)->accounting($organization, $claim);
        }

        return $claim;
    }

    public function openTicket(Employee $employee, int $categoryId, string $subject, string $priority): Ticket
    {
        $hours = SlaPolicy::query()->where('priority', $priority)->value('response_hours');

        return Ticket::query()->create([
            'employee_id' => $employee->id,
            'ticket_category_id' => $categoryId,
            'subject' => $subject,
            'priority' => $priority,
            'status' => 'open',
            'due_at' => $hours === null ? null : now()->addHours((int) $hours),
        ]);
    }

    public function resolve(Ticket $ticket, User $actor): Ticket
    {
        $ticket->status = 'resolved';
        $ticket->save();
        $this->audit->accessChange($actor, $this->context->get(), 'ticket.resolved', ['ticket_id' => $ticket->id]);

        return $ticket;
    }

    /**
     * @return array{birthdays: list<array{name: string, day: string}>, anniversaries: list<array{name: string, years: int}>}
     */
    public function reminders(): array
    {
        $month = (int) now()->format('n');
        $birthdays = [];
        foreach (Employee::query()->where('status', 'active')->whereNotNull('date_of_birth')->get() as $employee) {
            if ((int) $employee->date_of_birth->format('n') === $month) {
                $birthdays[] = ['name' => $employee->fullName(), 'day' => $employee->date_of_birth->format('d M')];
            }
        }
        $anniversaries = [];
        foreach (Employee::query()->where('status', 'active')->whereNotNull('joined_on')->get() as $employee) {
            if ((int) $employee->joined_on->format('n') === $month && $employee->joined_on->year < (int) now()->format('Y')) {
                $anniversaries[] = ['name' => $employee->fullName(), 'years' => (int) now()->format('Y') - (int) $employee->joined_on->format('Y')];
            }
        }

        return ['birthdays' => $birthdays, 'anniversaries' => $anniversaries];
    }

    private function assertOwnEmployee(int $employeeId, User $actor): void
    {
        $owns = Employee::query()->whereKey($employeeId)->where('user_id', $actor->id)->exists();
        if (! $owns) {
            throw ValidationException::withMessages(['employee' => ['This action belongs to the signed-in employee.']]);
        }
    }
}
