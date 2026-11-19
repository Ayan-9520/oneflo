<?php

declare(strict_types=1);

namespace App\Modules\Reports\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\LegalEntity;
use App\Models\ReportSchedule;
use App\Models\SavedReportFilter;
use App\Modules\Reports\ReportScope;
use App\Modules\Reports\ReportService;
use App\Services\AuthorizationService;
use App\Support\ApiResponse;
use App\Support\Permissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReportController extends Controller
{
    public function dashboard(Request $request, AuthorizationService $authorization, ReportService $reports): JsonResponse
    {
        $authorization->authorizePermission($request->user(), Permissions::REPORTS_VIEW);

        return ApiResponse::data($reports->dashboard($this->scope($request), $request->user()));
    }

    public function show(Request $request, string $report, AuthorizationService $authorization, ReportService $reports): JsonResponse
    {
        $authorization->authorizePermission($request->user(), Permissions::REPORTS_VIEW);
        $this->known($report);

        return ApiResponse::data($reports->run($report, $this->scope($request), $request->user()));
    }

    public function export(Request $request, string $report, AuthorizationService $authorization, ReportService $reports): \Symfony\Component\HttpFoundation\Response
    {
        $authorization->authorizePermission($request->user(), Permissions::REPORTS_EXPORT);
        $this->known($report);
        $format = $request->validate(['format' => ['required', Rule::in(['csv', 'xlsx', 'pdf'])]])['format'];
        $table = $reports->run($report, $this->scope($request), $request->user());
        $headers = ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff'];
        if ($format === 'csv') {
            return response($reports->csv($table), 200, [...$headers, 'Content-Type' => 'text/csv', 'Content-Disposition' => 'attachment; filename="'.$report.'.csv"']);
        }
        if ($format === 'xlsx') {
            return response($reports->xlsx($table), 200, [...$headers, 'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'Content-Disposition' => 'attachment; filename="'.$report.'.xlsx"']);
        }

        return response($reports->pdf($report, $table), 200, [...$headers, 'Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="'.$report.'.pdf"']);
    }

    public function filters(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $authorization->authorizePermission($request->user(), Permissions::REPORTS_VIEW);

        return ApiResponse::data(SavedReportFilter::query()->where('user_id', $request->user()->id)->orderBy('name')->get()->map(fn (SavedReportFilter $filter) => [
            'id' => $filter->id, 'name' => $filter->name, 'report' => $filter->report, 'filters' => $filter->filters,
        ])->all());
    }

    public function showFilter(SavedReportFilter $savedReportFilter, AuthorizationService $authorization): JsonResponse
    {
        $authorization->authorizePermission(request()->user(), Permissions::REPORTS_VIEW);
        if ((int) $savedReportFilter->user_id !== (int) request()->user()->id) {
            abort(404, 'The requested resource was not found.');
        }

        return ApiResponse::data(['id' => $savedReportFilter->id, 'name' => $savedReportFilter->name, 'report' => $savedReportFilter->report, 'filters' => $savedReportFilter->filters]);
    }

    public function storeFilter(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $authorization->authorizePermission($request->user(), Permissions::REPORTS_VIEW);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'report' => ['required', Rule::in(ReportService::REPORTS)],
            'filters' => ['present', 'array'],
        ]);
        $filter = SavedReportFilter::query()->updateOrCreate(
            ['user_id' => $request->user()->id, 'name' => $data['name']],
            ['report' => $data['report'], 'filters' => $data['filters']],
        );

        return ApiResponse::data(['id' => $filter->id], 201);
    }

    public function schedules(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $authorization->authorizePermission($request->user(), Permissions::REPORTS_EXPORT);

        return ApiResponse::data(ReportSchedule::query()->where('user_id', $request->user()->id)->orderBy('id')->get(['id', 'report', 'format', 'cadence', 'next_run_on'])->all());
    }

    public function storeSchedule(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $authorization->authorizePermission($request->user(), Permissions::REPORTS_EXPORT);
        $data = $request->validate([
            'report' => ['required', Rule::in(ReportService::REPORTS)],
            'filters' => ['present', 'array'],
            'format' => ['required', Rule::in(['csv', 'xlsx', 'pdf'])],
            'cadence' => ['required', Rule::in(['daily', 'weekly', 'monthly'])],
            'next_run_on' => ['required', 'date'],
        ]);
        $schedule = ReportSchedule::query()->create([...$data, 'user_id' => $request->user()->id]);

        return ApiResponse::data(['id' => $schedule->id, 'next_run_on' => $schedule->next_run_on?->toDateString()], 201);
    }

    private function known(string $report): void
    {
        if (! in_array($report, ReportService::REPORTS, true)) {
            abort(404, 'The requested resource was not found.');
        }
    }

    private function scope(Request $request): ReportScope
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'legal_entity_id' => ['nullable', 'integer'],
            'department_id' => ['nullable', 'integer'],
            'branch_id' => ['nullable', 'integer'],
            'designation_id' => ['nullable', 'integer'],
            'employee_id' => ['nullable', 'integer'],
            'organization_id' => ['prohibited'],
        ]);
        $this->exists(LegalEntity::class, $data['legal_entity_id'] ?? null);
        $this->exists(Department::class, $data['department_id'] ?? null);
        $this->exists(Branch::class, $data['branch_id'] ?? null);
        $this->exists(Designation::class, $data['designation_id'] ?? null);
        $this->exists(Employee::class, $data['employee_id'] ?? null);

        return ReportScope::fromArray($data);
    }

    /**
     * @param  class-string  $model
     */
    private function exists(string $model, mixed $id): void
    {
        if ($id !== null && $model::query()->whereKey($id)->doesntExist()) {
            abort(404, 'The requested resource was not found.');
        }
    }
}
