<?php

declare(strict_types=1);

namespace App\Modules\Operations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AssetAllocation;
use App\Models\AssetMaintenance;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeFeedback;
use App\Models\ExpenseCategory;
use App\Models\ExpenseClaim;
use App\Models\Goal;
use App\Models\Kpi;
use App\Models\PerformanceReview;
use App\Models\Post;
use App\Models\Recognition;
use App\Models\SlaPolicy;
use App\Models\Survey;
use App\Models\SurveyResponse;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\TicketCategory;
use App\Models\TicketComment;
use App\Modules\Operations\Services\OperationsService;
use App\Services\AuthorizationService;
use App\Services\OrganizationContext;
use App\Support\ApiResponse;
use App\Support\Permissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class OperationsController extends Controller
{
    public function kpis(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $this->allow($request, $authorization, Permissions::PERFORMANCE_VIEW);

        return ApiResponse::data(Kpi::query()->orderBy('name')->get(['id', 'name', 'unit', 'weight'])->all());
    }

    public function storeKpi(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $this->allow($request, $authorization, Permissions::PERFORMANCE_MANAGE);
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'unit' => ['required', 'string', 'max:40'], 'weight' => ['required', 'integer', 'min:1', 'max:100']]);

        return ApiResponse::data(Kpi::query()->create($data)->only(['id', 'name']), 201);
    }

    public function goals(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $this->allow($request, $authorization, Permissions::PERFORMANCE_VIEW);

        return ApiResponse::data(Goal::query()->orderByDesc('id')->get()->map(fn (Goal $goal) => [
            'id' => $goal->id, 'scope' => $goal->scope, 'title' => $goal->title, 'period' => $goal->period, 'target' => $goal->target, 'status' => $goal->status,
        ])->all());
    }

    public function storeGoal(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $this->allow($request, $authorization, Permissions::PERFORMANCE_MANAGE);
        $data = $request->validate([
            'scope' => ['required', Rule::in(['employee', 'department'])],
            'employee_id' => ['nullable', 'integer'],
            'department_id' => ['nullable', 'integer'],
            'kpi_id' => ['nullable', 'integer'],
            'title' => ['required', 'string', 'max:160'],
            'period' => ['required', Rule::in(['monthly', 'quarterly', 'annual'])],
            'period_on' => ['required', 'date'],
            'target' => ['required', 'string', 'max:120'],
        ]);
        if (($data['employee_id'] ?? null) && Employee::query()->whereKey($data['employee_id'])->doesntExist()) {
            abort(404, 'The requested resource was not found.');
        }
        if (($data['department_id'] ?? null) && Department::query()->whereKey($data['department_id'])->doesntExist()) {
            abort(404, 'The requested resource was not found.');
        }
        $goal = Goal::query()->create([...$data, 'status' => 'open']);

        return ApiResponse::data(['id' => $goal->id], 201);
    }

    public function reviews(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $this->allow($request, $authorization, Permissions::PERFORMANCE_VIEW);
        $query = PerformanceReview::query()->with('employee')->orderByDesc('period_on');
        if (! $this->seesAll($request, $authorization, Permissions::PERFORMANCE_MANAGE)) {
            $query->where('employee_id', $this->ownEmployee($request)?->id ?? 0);
        }

        return ApiResponse::data($query->get()->map(fn (PerformanceReview $review) => [
            'id' => $review->id,
            'employee_id' => $review->employee_id,
            'employee' => $review->employee?->fullName(),
            'cycle' => $review->cycle,
            'period_on' => $review->period_on?->toDateString(),
            'self_score' => $review->self_score,
            'manager_score' => $review->manager_score,
            'promotion_recommended' => $review->promotion_recommended,
            'status' => $review->status,
        ])->all());
    }

    public function storeReview(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $this->allow($request, $authorization, Permissions::PERFORMANCE_MANAGE);
        $data = $request->validate([
            'employee_id' => ['required', 'integer'],
            'cycle' => ['required', Rule::in(['monthly', 'quarterly', 'annual'])],
            'period_on' => ['required', 'date'],
        ]);
        if (Employee::query()->whereKey($data['employee_id'])->doesntExist()) {
            abort(404, 'The requested resource was not found.');
        }
        $review = PerformanceReview::query()->create([...$data, 'status' => 'draft', 'promotion_recommended' => false]);
        $organization = app(OrganizationContext::class)->get();
        if ($organization !== null) {
            $review->load('employee.user');
            app(\App\Modules\Notifications\NotificationService::class)->publish($organization, [$review->employee?->user], \App\Modules\Notifications\NotificationEvents::PERFORMANCE_REVIEW, 'review:'.$review->id, 'Performance review', 'A '.$review->cycle.' review is open.');
        }

        return ApiResponse::data(['id' => $review->id], 201);
    }

    public function selfReview(Request $request, PerformanceReview $performanceReview, AuthorizationService $authorization, OperationsService $operations): JsonResponse
    {
        $this->allow($request, $authorization, Permissions::PERFORMANCE_VIEW);
        $data = $request->validate(['score' => ['required', 'integer', 'between:1,5'], 'comments' => ['required', 'string']]);
        $review = $operations->submitSelfReview($performanceReview, $request->user(), (int) $data['score'], $data['comments']);

        return ApiResponse::data(['status' => $review->status]);
    }

    public function completeReview(Request $request, PerformanceReview $performanceReview, AuthorizationService $authorization, OperationsService $operations): JsonResponse
    {
        $this->allow($request, $authorization, Permissions::PERFORMANCE_MANAGE);
        $data = $request->validate(['score' => ['required', 'integer', 'between:1,5'], 'comments' => ['required', 'string'], 'promotion_recommended' => ['required', 'boolean']]);
        $review = $operations->completeReview($performanceReview, $request->user(), (int) $data['score'], $data['comments'], (bool) $data['promotion_recommended']);

        return ApiResponse::data(['status' => $review->status, 'manager_score' => $review->manager_score]);
    }

    public function assets(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $this->allow($request, $authorization, Permissions::ASSETS_VIEW);

        $open = AssetAllocation::query()->whereNull('returned_on')->get(['id', 'asset_id'])->keyBy('asset_id');

        return ApiResponse::data(Asset::query()->orderBy('asset_code')->get(['id', 'asset_code', 'category', 'name', 'condition', 'status'])->map(fn (Asset $asset) => [
            'id' => $asset->id,
            'asset_code' => $asset->asset_code,
            'category' => $asset->category,
            'name' => $asset->name,
            'condition' => $asset->condition,
            'status' => $asset->status,
            'allocation_id' => $open->get($asset->id)?->id,
        ])->all());
    }

    public function storeAsset(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $this->allow($request, $authorization, Permissions::ASSETS_MANAGE);
        $data = $request->validate([
            'asset_code' => ['required', 'string', 'max:40'],
            'category' => ['required', Rule::in(['laptop', 'mobile', 'id_card', 'accessory', 'other'])],
            'name' => ['required', 'string', 'max:120'],
            'condition' => ['required', Rule::in(['good', 'fair', 'damaged'])],
        ]);
        $asset = Asset::query()->create([...$data, 'status' => 'in_stock']);

        return ApiResponse::data(['id' => $asset->id], 201);
    }

    public function assignAsset(Request $request, Asset $asset, AuthorizationService $authorization, OperationsService $operations): JsonResponse
    {
        $this->allow($request, $authorization, Permissions::ASSETS_MANAGE);
        $data = $request->validate(['employee_id' => ['required', 'integer'], 'allocated_on' => ['required', 'date']]);
        $employee = Employee::query()->find($data['employee_id']);
        if ($employee === null) {
            abort(404, 'The requested resource was not found.');
        }
        $allocation = $operations->assignAsset($asset, $employee, $data['allocated_on'], $request->user());

        return ApiResponse::data(['id' => $allocation->id], 201);
    }

    public function returnAsset(Request $request, AssetAllocation $assetAllocation, AuthorizationService $authorization, OperationsService $operations): JsonResponse
    {
        $this->allow($request, $authorization, Permissions::ASSETS_MANAGE);
        $data = $request->validate(['returned_on' => ['required', 'date'], 'condition' => ['required', Rule::in(['good', 'fair', 'damaged'])]]);
        $operations->returnAsset($assetAllocation, $data['returned_on'], $data['condition'], $request->user());

        return ApiResponse::data(['returned' => true]);
    }

    public function maintainAsset(Request $request, Asset $asset, AuthorizationService $authorization): JsonResponse
    {
        $this->allow($request, $authorization, Permissions::ASSETS_MANAGE);
        $data = $request->validate(['performed_on' => ['required', 'date'], 'notes' => ['required', 'string', 'max:240']]);
        $asset->status = 'maintenance';
        $asset->save();
        AssetMaintenance::query()->create(['asset_id' => $asset->id, ...$data]);

        return ApiResponse::data(['status' => 'maintenance']);
    }

    public function assetHistory(Request $request, Employee $employee, AuthorizationService $authorization): JsonResponse
    {
        $this->allow($request, $authorization, Permissions::ASSETS_VIEW);

        return ApiResponse::data(AssetAllocation::query()->with('asset')->where('employee_id', $employee->id)->orderByDesc('id')->get()->map(fn (AssetAllocation $row) => [
            'asset_code' => $row->asset?->asset_code,
            'name' => $row->asset?->name,
            'allocated_on' => $row->allocated_on?->toDateString(),
            'returned_on' => $row->returned_on?->toDateString(),
            'return_condition' => $row->return_condition,
        ])->all());
    }

    public function expenseCategories(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $this->either($request, $authorization, [Permissions::EXPENSES_VIEW, Permissions::EXPENSES_SUBMIT]);

        return ApiResponse::data(ExpenseCategory::query()->orderBy('name')->get(['id', 'name'])->all());
    }

    public function storeExpenseCategory(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $this->allow($request, $authorization, Permissions::EXPENSES_APPROVE);
        $data = $request->validate(['name' => ['required', 'string', 'max:80']]);

        return ApiResponse::data(ExpenseCategory::query()->create($data)->only(['id', 'name']), 201);
    }

    public function expenses(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $this->either($request, $authorization, [Permissions::EXPENSES_VIEW, Permissions::EXPENSES_SUBMIT]);
        $query = ExpenseClaim::query()->with('employee', 'category')->orderByDesc('id');
        if (! $this->seesAll($request, $authorization, Permissions::EXPENSES_APPROVE)) {
            $query->where('employee_id', $this->ownEmployee($request)?->id ?? 0);
        }

        return ApiResponse::data($query->get()->map(fn (ExpenseClaim $claim) => [
            'id' => $claim->id,
            'employee' => $claim->employee?->fullName(),
            'category' => $claim->category?->name,
            'amount' => number_format((float) $claim->amount, 2, '.', ''),
            'status' => $claim->status,
            'rejection_reason' => $claim->rejection_reason,
        ])->all());
    }

    public function storeExpense(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $this->allow($request, $authorization, Permissions::EXPENSES_SUBMIT);
        $employee = $this->ownEmployee($request);
        if ($employee === null) {
            abort(403, 'You are not allowed to perform this action.');
        }
        $data = $request->validate([
            'expense_category_id' => ['required', 'integer'],
            'amount' => ['required', 'numeric', 'min:0'],
            'incurred_on' => ['required', 'date'],
            'description' => ['required', 'string', 'max:240'],
        ]);
        if (ExpenseCategory::query()->whereKey($data['expense_category_id'])->doesntExist()) {
            abort(404, 'The requested resource was not found.');
        }
        $claim = ExpenseClaim::query()->create([...$data, 'employee_id' => $employee->id, 'status' => 'submitted']);
        $organization = app(\App\Services\OrganizationContext::class)->get();
        if ($organization !== null) {
            app(\App\Modules\Saas\WorkflowRunner::class)->dispatch($organization, 'expense.submitted', 'expense:'.$claim->id);
        }

        return ApiResponse::data(['id' => $claim->id, 'status' => $claim->status], 201);
    }

    public function storeReceipt(Request $request, ExpenseClaim $expenseClaim, AuthorizationService $authorization): JsonResponse
    {
        $this->allow($request, $authorization, Permissions::EXPENSES_SUBMIT);
        if ($expenseClaim->employee->user_id !== $request->user()->id) {
            abort(403, 'You are not allowed to perform this action.');
        }
        $request->validate(['receipt' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120']]);
        $path = $request->file('receipt')->store('receipts/'.app(OrganizationContext::class)->id(), 'documents');
        $expenseClaim->receipt_path = $path;
        $expenseClaim->save();

        return ApiResponse::data(['stored' => true]);
    }

    public function managerExpense(Request $request, ExpenseClaim $expenseClaim, AuthorizationService $authorization, OperationsService $operations): JsonResponse
    {
        $this->allow($request, $authorization, Permissions::EXPENSES_APPROVE);

        return ApiResponse::data(['status' => $operations->managerApprove($expenseClaim, $request->user())->status]);
    }

    public function financeExpense(Request $request, ExpenseClaim $expenseClaim, AuthorizationService $authorization, OperationsService $operations): JsonResponse
    {
        $this->allow($request, $authorization, Permissions::EXPENSES_APPROVE);

        return ApiResponse::data(['status' => $operations->financeApprove($expenseClaim, $request->user())->status]);
    }

    public function rejectExpense(Request $request, ExpenseClaim $expenseClaim, AuthorizationService $authorization, OperationsService $operations): JsonResponse
    {
        $this->allow($request, $authorization, Permissions::EXPENSES_APPROVE);
        $data = $request->validate(['reason' => ['required', 'string', 'max:240']]);

        return ApiResponse::data(['status' => $operations->reject($expenseClaim, $request->user(), $data['reason'])->status]);
    }

    public function resubmitExpense(Request $request, ExpenseClaim $expenseClaim, AuthorizationService $authorization, OperationsService $operations): JsonResponse
    {
        $this->allow($request, $authorization, Permissions::EXPENSES_SUBMIT);

        return ApiResponse::data(['status' => $operations->resubmit($expenseClaim, $request->user())->status]);
    }

    public function reimburseExpense(Request $request, ExpenseClaim $expenseClaim, AuthorizationService $authorization, OperationsService $operations): JsonResponse
    {
        $this->allow($request, $authorization, Permissions::EXPENSES_APPROVE);

        return ApiResponse::data(['status' => $operations->reimburse($expenseClaim, $request->user())->status]);
    }

    public function ticketCategories(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $this->allow($request, $authorization, Permissions::HELPDESK_VIEW);

        return ApiResponse::data(TicketCategory::query()->orderBy('name')->get(['id', 'name'])->all());
    }

    public function storeTicketCategory(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $this->allow($request, $authorization, Permissions::HELPDESK_MANAGE);
        $data = $request->validate(['name' => ['required', 'string', 'max:80']]);

        return ApiResponse::data(TicketCategory::query()->create($data)->only(['id', 'name']), 201);
    }

    public function storeSla(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $this->allow($request, $authorization, Permissions::HELPDESK_MANAGE);
        $data = $request->validate(['priority' => ['required', Rule::in(['low', 'medium', 'high', 'urgent'])], 'response_hours' => ['required', 'integer', 'min:1']]);
        $policy = SlaPolicy::query()->updateOrCreate(['priority' => $data['priority']], ['response_hours' => $data['response_hours']]);

        return ApiResponse::data(['id' => $policy->id]);
    }

    public function tickets(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $this->allow($request, $authorization, Permissions::HELPDESK_VIEW);
        $query = Ticket::query()->orderByDesc('id');
        if (! $authorization->hasPermission($request->user(), Permissions::HELPDESK_MANAGE, app(OrganizationContext::class)->get())) {
            $query->where('employee_id', $this->ownEmployee($request)?->id ?? 0);
        }

        return ApiResponse::data($query->get()->map(fn (Ticket $ticket) => [
            'id' => $ticket->id, 'subject' => $ticket->subject, 'priority' => $ticket->priority, 'status' => $ticket->status, 'due_at' => $ticket->due_at?->toIso8601String(),
        ])->all());
    }

    public function storeTicket(Request $request, AuthorizationService $authorization, OperationsService $operations): JsonResponse
    {
        $this->allow($request, $authorization, Permissions::HELPDESK_VIEW);
        $employee = $this->ownEmployee($request);
        if ($employee === null && $authorization->hasPermission($request->user(), Permissions::HELPDESK_MANAGE, app(OrganizationContext::class)->get())) {
            $dataEmployee = $request->validate(['employee_id' => ['required', 'integer']]);
            $employee = Employee::query()->find($dataEmployee['employee_id']);
        }
        if ($employee === null) {
            abort(403, 'You are not allowed to perform this action.');
        }
        $data = $request->validate([
            'ticket_category_id' => ['required', 'integer'],
            'subject' => ['required', 'string', 'max:160'],
            'priority' => ['required', Rule::in(['low', 'medium', 'high', 'urgent'])],
        ]);
        if (TicketCategory::query()->whereKey($data['ticket_category_id'])->doesntExist()) {
            abort(404, 'The requested resource was not found.');
        }
        $ticket = $operations->openTicket($employee, (int) $data['ticket_category_id'], $data['subject'], $data['priority']);

        return ApiResponse::data(['id' => $ticket->id, 'due_at' => $ticket->due_at?->toIso8601String()], 201);
    }

    public function assignTicket(Request $request, Ticket $ticket, AuthorizationService $authorization): JsonResponse
    {
        $this->allow($request, $authorization, Permissions::HELPDESK_MANAGE);
        $data = $request->validate(['assignee_user_id' => ['required', 'integer']]);
        $ticket->assignee_user_id = $data['assignee_user_id'];
        $ticket->status = 'assigned';
        $ticket->save();

        return ApiResponse::data(['status' => $ticket->status]);
    }

    public function commentTicket(Request $request, Ticket $ticket, AuthorizationService $authorization): JsonResponse
    {
        $this->allow($request, $authorization, Permissions::HELPDESK_VIEW);
        $data = $request->validate(['body' => ['required', 'string']]);
        TicketComment::query()->create(['ticket_id' => $ticket->id, 'user_id' => $request->user()->id, 'body' => $data['body']]);

        return ApiResponse::data(['recorded' => true], 201);
    }

    public function attachTicket(Request $request, Ticket $ticket, AuthorizationService $authorization): JsonResponse
    {
        $this->allow($request, $authorization, Permissions::HELPDESK_VIEW);
        $request->validate(['file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120']]);
        $file = $request->file('file');
        $path = $file->store('tickets/'.app(OrganizationContext::class)->id(), 'documents');
        TicketAttachment::query()->create(['ticket_id' => $ticket->id, 'original_name' => $file->getClientOriginalName(), 'path' => $path]);

        return ApiResponse::data(['stored' => true], 201);
    }

    public function resolveTicket(Request $request, Ticket $ticket, AuthorizationService $authorization, OperationsService $operations): JsonResponse
    {
        $this->allow($request, $authorization, Permissions::HELPDESK_MANAGE);

        return ApiResponse::data(['status' => $operations->resolve($ticket, $request->user())->status]);
    }

    public function ticketHistory(Request $request, Ticket $ticket, AuthorizationService $authorization): JsonResponse
    {
        $this->allow($request, $authorization, Permissions::HELPDESK_VIEW);

        return ApiResponse::data(TicketComment::query()->where('ticket_id', $ticket->id)->orderBy('id')->get(['body', 'created_at'])->all());
    }

    public function posts(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $this->allow($request, $authorization, Permissions::ENGAGEMENT_VIEW);

        return ApiResponse::data(Post::query()->orderByDesc('id')->get()->map(fn (Post $post) => [
            'id' => $post->id, 'kind' => $post->kind, 'title' => $post->title, 'body' => $post->body, 'occurs_on' => $post->occurs_on?->toDateString(),
        ])->all());
    }

    public function storePost(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $this->allow($request, $authorization, Permissions::ENGAGEMENT_MANAGE);
        $data = $request->validate([
            'kind' => ['required', Rule::in(['announcement', 'news', 'event'])],
            'title' => ['required', 'string', 'max:160'],
            'body' => ['required', 'string'],
            'occurs_on' => ['nullable', 'date'],
        ]);

        return ApiResponse::data(Post::query()->create($data)->only(['id', 'title']), 201);
    }

    public function feedback(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $this->allow($request, $authorization, Permissions::ENGAGEMENT_VIEW);
        $manage = $authorization->hasPermission($request->user(), Permissions::ENGAGEMENT_MANAGE, app(OrganizationContext::class)->get());

        return ApiResponse::data(EmployeeFeedback::query()->with('employee')->orderByDesc('id')->get()->map(fn (EmployeeFeedback $row) => [
            'id' => $row->id,
            'body' => $row->body,
            'anonymous' => $row->anonymous,
            'employee' => $row->anonymous && ! $manage ? null : $row->employee?->fullName(),
        ])->all());
    }

    public function storeFeedback(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $this->allow($request, $authorization, Permissions::ENGAGEMENT_VIEW);
        $employee = $this->ownEmployee($request);
        if ($employee === null) {
            abort(403, 'You are not allowed to perform this action.');
        }
        $data = $request->validate(['body' => ['required', 'string'], 'anonymous' => ['required', 'boolean']]);
        EmployeeFeedback::query()->create(['employee_id' => $employee->id, 'body' => $data['body'], 'anonymous' => $data['anonymous']]);

        return ApiResponse::data(['recorded' => true], 201);
    }

    public function surveys(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $this->allow($request, $authorization, Permissions::ENGAGEMENT_VIEW);

        return ApiResponse::data(Survey::query()->orderByDesc('id')->get(['id', 'title', 'question'])->all());
    }

    public function storeSurvey(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $this->allow($request, $authorization, Permissions::ENGAGEMENT_MANAGE);
        $data = $request->validate(['title' => ['required', 'string', 'max:160'], 'question' => ['required', 'string', 'max:240']]);

        return ApiResponse::data(Survey::query()->create($data)->only(['id', 'title']), 201);
    }

    public function respondSurvey(Request $request, Survey $survey, AuthorizationService $authorization): JsonResponse
    {
        $this->allow($request, $authorization, Permissions::ENGAGEMENT_VIEW);
        $employee = $this->ownEmployee($request);
        if ($employee === null) {
            abort(403, 'You are not allowed to perform this action.');
        }
        $data = $request->validate(['answer' => ['required', 'string']]);
        SurveyResponse::query()->updateOrCreate(['survey_id' => $survey->id, 'employee_id' => $employee->id], ['answer' => $data['answer']]);

        return ApiResponse::data(['recorded' => true]);
    }

    public function recognitions(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $this->allow($request, $authorization, Permissions::ENGAGEMENT_VIEW);

        return ApiResponse::data(Recognition::query()->with('employee')->orderByDesc('id')->get()->map(fn (Recognition $row) => [
            'employee' => $row->employee?->fullName(), 'message' => $row->message, 'awarded_on' => $row->awarded_on?->toDateString(),
        ])->all());
    }

    public function storeRecognition(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $this->allow($request, $authorization, Permissions::ENGAGEMENT_MANAGE);
        $data = $request->validate(['employee_id' => ['required', 'integer'], 'message' => ['required', 'string', 'max:240'], 'awarded_on' => ['required', 'date']]);
        if (Employee::query()->whereKey($data['employee_id'])->doesntExist()) {
            abort(404, 'The requested resource was not found.');
        }
        Recognition::query()->create($data);

        return ApiResponse::data(['recorded' => true], 201);
    }

    public function reminders(Request $request, AuthorizationService $authorization, OperationsService $operations): JsonResponse
    {
        $this->allow($request, $authorization, Permissions::ENGAGEMENT_VIEW);

        return ApiResponse::data($operations->reminders());
    }

    public function receipt(Request $request, ExpenseClaim $expenseClaim, AuthorizationService $authorization): \Symfony\Component\HttpFoundation\Response
    {
        $this->either($request, $authorization, [Permissions::EXPENSES_VIEW, Permissions::EXPENSES_SUBMIT]);
        if ($expenseClaim->receipt_path === null) {
            abort(404, 'The requested resource was not found.');
        }

        return response(Storage::disk('documents')->get($expenseClaim->receipt_path), 200, [
            'Content-Type' => 'application/octet-stream',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function allow(Request $request, AuthorizationService $authorization, string $permission): void
    {
        $authorization->authorizePermission($request->user(), $permission);
    }

    /**
     * @param  list<string>  $permissions
     */
    private function either(Request $request, AuthorizationService $authorization, array $permissions): void
    {
        $organization = app(OrganizationContext::class)->get();
        foreach ($permissions as $permission) {
            if ($authorization->hasPermission($request->user(), $permission, $organization)) {
                return;
            }
        }
        abort(403, 'You are not allowed to perform this action.');
    }

    private function seesAll(Request $request, AuthorizationService $authorization, string $permission): bool
    {
        return $authorization->hasPermission($request->user(), $permission, app(OrganizationContext::class)->get())
            || $authorization->hasPermission($request->user(), Permissions::EMPLOYEES_VIEW, app(OrganizationContext::class)->get());
    }

    private function ownEmployee(Request $request): ?Employee
    {
        return Employee::query()->where('user_id', $request->user()->id)->first();
    }
}
