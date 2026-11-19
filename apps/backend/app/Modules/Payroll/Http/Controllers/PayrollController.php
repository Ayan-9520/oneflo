<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeCompensation;
use App\Models\LegalEntity;
use App\Models\PayrollComponent;
use App\Models\PayrollLine;
use App\Models\PayrollRun;
use App\Models\SalaryStructure;
use App\Models\StatutoryRule;
use App\Modules\Payroll\Services\PayrollService;
use App\Services\AuthorizationService;
use App\Services\OrganizationContext;
use App\Support\ApiResponse;
use App\Support\SpreadsheetFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PayrollController extends Controller
{
    public function components(AuthorizationService $authorization): JsonResponse
    {
        $this->view($authorization);

        return ApiResponse::data(PayrollComponent::query()->orderBy('name')->get(['id', 'code', 'name', 'kind', 'taxable'])->all());
    }

    public function storeComponent(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $this->process($request, $authorization);
        $data = $request->validate([
            'code' => ['required', 'string', 'max:40'],
            'name' => ['required', 'string', 'max:120'],
            'kind' => ['required', Rule::in(['earning', 'deduction', 'employer_contribution', 'reimbursement', 'variable'])],
            'taxable' => ['required', 'boolean'],
            'organization_id' => ['prohibited'],
        ]);

        return ApiResponse::data(PayrollComponent::query()->create($data)->only(['id', 'code', 'name']), 201);
    }

    public function structures(AuthorizationService $authorization): JsonResponse
    {
        $this->view($authorization);

        return ApiResponse::data(SalaryStructure::query()->with('lines')->orderByDesc('id')->get()->map(fn (SalaryStructure $structure) => [
            'id' => $structure->id,
            'legal_entity_id' => $structure->legal_entity_id,
            'name' => $structure->name,
            'effective_on' => $structure->effective_on?->toDateString(),
            'lines' => $structure->lines->map(fn ($line) => [
                'payroll_component_id' => $line->payroll_component_id,
                'monthly_amount' => $line->monthly_amount,
            ])->all(),
        ])->all());
    }

    public function rules(AuthorizationService $authorization): JsonResponse
    {
        $this->view($authorization);

        return ApiResponse::data(StatutoryRule::query()->orderByDesc('effective_on')->get()->map(fn (StatutoryRule $rule) => [
            'id' => $rule->id,
            'legal_entity_id' => $rule->legal_entity_id,
            'code' => $rule->code,
            'effective_on' => $rule->effective_on?->toDateString(),
            'ended_on' => $rule->ended_on?->toDateString(),
            'citation' => $rule->citation,
            'confirmed' => $rule->confirmed_at !== null,
        ])->all());
    }

    public function storeStructure(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $this->process($request, $authorization);
        $data = $request->validate([
            'legal_entity_id' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:120'],
            'effective_on' => ['required', 'date'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.payroll_component_id' => ['required', 'integer'],
            'lines.*.monthly_amount' => ['required', 'numeric', 'min:0'],
            'organization_id' => ['prohibited'],
        ]);
        if (LegalEntity::query()->whereKey($data['legal_entity_id'])->doesntExist()) {
            abort(404, 'The requested resource was not found.');
        }
        $structure = SalaryStructure::query()->create([
            'legal_entity_id' => $data['legal_entity_id'],
            'name' => $data['name'],
            'effective_on' => $data['effective_on'],
        ]);
        foreach ($data['lines'] as $line) {
            if (PayrollComponent::query()->whereKey($line['payroll_component_id'])->doesntExist()) {
                return ApiResponse::error('The salary component is not in this business.', 422);
            }
            $structure->lines()->create($line);
        }

        return ApiResponse::data(['id' => $structure->id], 201);
    }

    public function assignCompensation(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $this->process($request, $authorization);
        $data = $request->validate([
            'employee_id' => ['required', 'integer'],
            'salary_structure_id' => ['required', 'integer'],
            'tax_regime' => ['required', Rule::in(['new', 'old'])],
            'pan' => ['nullable', 'string', 'max:10'],
            'organization_id' => ['prohibited'],
        ]);
        $employee = Employee::query()->find($data['employee_id']);
        if ($employee === null || ! $authorization->canViewEmployee($request->user(), $employee)) {
            abort(404, 'The requested resource was not found.');
        }
        $compensation = EmployeeCompensation::query()->updateOrCreate(
            ['employee_id' => $employee->id],
            ['salary_structure_id' => $data['salary_structure_id'], 'tax_regime' => $data['tax_regime'], 'pan' => $data['pan'] ?? null],
        );

        return ApiResponse::data(['id' => $compensation->id, 'pan' => $compensation->maskedPan()]);
    }

    public function storeRule(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $this->process($request, $authorization);
        $data = $request->validate([
            'legal_entity_id' => ['required', 'integer'],
            'code' => ['required', Rule::in(['epf', 'esi', 'professional_tax', 'tds', 'tds_old', 'bonus', 'gratuity'])],
            'effective_on' => ['required', 'date'],
            'ended_on' => ['nullable', 'date'],
            'config' => ['required', 'array'],
            'citation' => ['required', 'string', 'max:240'],
            'organization_id' => ['prohibited'],
        ]);
        if (LegalEntity::query()->whereKey($data['legal_entity_id'])->doesntExist()) {
            abort(404, 'The requested resource was not found.');
        }
        $rule = StatutoryRule::query()->create($data);

        return ApiResponse::data(['id' => $rule->id, 'confirmed' => false], 201);
    }

    public function confirmRule(Request $request, StatutoryRule $statutoryRule, AuthorizationService $authorization): JsonResponse
    {
        $this->process($request, $authorization);
        $statutoryRule->confirmed_at = now();
        $statutoryRule->save();

        return ApiResponse::data(['id' => $statutoryRule->id, 'confirmed' => true]);
    }

    public function runs(AuthorizationService $authorization): JsonResponse
    {
        $this->view($authorization);

        return ApiResponse::data(PayrollRun::query()->with('lines.employee')->orderByDesc('id')->limit(24)->get()->map(fn (PayrollRun $run) => $this->runPayload($run, false))->all());
    }

    public function storeRun(Request $request, AuthorizationService $authorization, PayrollService $payroll): JsonResponse
    {
        $this->process($request, $authorization);
        $data = $request->validate([
            'legal_entity_id' => ['required', 'integer'],
            'year' => ['required', 'integer'],
            'month' => ['required', 'integer', 'between:1,12'],
            'organization_id' => ['prohibited'],
        ]);
        $entity = LegalEntity::query()->find($data['legal_entity_id']);
        if ($entity === null) {
            abort(404, 'The requested resource was not found.');
        }

        return ApiResponse::data($this->runPayload($payroll->openRun($entity, (int) $data['year'], (int) $data['month']), false), 201);
    }

    public function show(PayrollRun $payrollRun, AuthorizationService $authorization): JsonResponse
    {
        $this->view($authorization);
        $detail = $authorization->hasPermission(request()->user(), 'payroll.process', app(OrganizationContext::class)->get());

        return ApiResponse::data($this->runPayload($payrollRun->load('lines.employee'), $detail));
    }

    public function preview(Request $request, PayrollRun $payrollRun, AuthorizationService $authorization, PayrollService $payroll): JsonResponse
    {
        $this->process($request, $authorization);

        return ApiResponse::data($this->runPayload($payroll->preview($payrollRun, $request->user())->load('lines.employee'), true));
    }

    public function submit(Request $request, PayrollRun $payrollRun, AuthorizationService $authorization, PayrollService $payroll): JsonResponse
    {
        $this->process($request, $authorization);

        return ApiResponse::data($this->runPayload($payroll->submit($payrollRun, $request->user()), false));
    }

    public function approve(Request $request, PayrollRun $payrollRun, AuthorizationService $authorization, PayrollService $payroll): JsonResponse
    {
        $this->process($request, $authorization);

        return ApiResponse::data($this->runPayload($payroll->approve($payrollRun, $request->user()), false));
    }

    public function lock(Request $request, PayrollRun $payrollRun, AuthorizationService $authorization, PayrollService $payroll): JsonResponse
    {
        $this->process($request, $authorization);

        return ApiResponse::data($this->runPayload($payroll->lock($payrollRun, $request->user()), false));
    }

    public function reverse(Request $request, PayrollRun $payrollRun, AuthorizationService $authorization, PayrollService $payroll): JsonResponse
    {
        $this->process($request, $authorization);
        $data = $request->validate(['reason' => ['required', 'string', 'max:240']]);

        return ApiResponse::data($this->runPayload($payroll->reverse($payrollRun, $request->user(), $data['reason']), false));
    }

    public function adjust(Request $request, PayrollRun $payrollRun, AuthorizationService $authorization, PayrollService $payroll): JsonResponse
    {
        $this->process($request, $authorization);
        $data = $request->validate([
            'employee_id' => ['required', 'integer'],
            'component_code' => ['required', 'string', 'max:40'],
            'amount' => ['required', 'numeric'],
            'reason' => ['required', 'string', 'max:240'],
        ]);
        $employee = Employee::query()->find($data['employee_id']);
        if ($employee === null) {
            abort(404, 'The requested resource was not found.');
        }
        $payroll->adjust($payrollRun, $employee, $data['component_code'], (int) round(((float) $data['amount']) * 100), $data['reason'], $request->user());

        return ApiResponse::data(['recorded' => true]);
    }

    public function payslip(PayrollLine $payrollLine, AuthorizationService $authorization, PayrollService $payroll): \Symfony\Component\HttpFoundation\Response
    {
        $payrollLine->load('run', 'employee');
        $organization = app(OrganizationContext::class)->get();
        $own = $payrollLine->employee->user_id === request()->user()->id
            && $payrollLine->run->status === 'locked'
            && ($authorization->hasPermission(request()->user(), 'payroll.view_own', $organization)
                || $authorization->hasPermission(request()->user(), 'employees.view_own', $organization));
        if (! $own) {
            $this->view($authorization);
        }
        $pdf = $payroll->payslipPdf($payrollLine);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="payslip-'.$payrollLine->id.'.pdf"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function register(PayrollRun $payrollRun, AuthorizationService $authorization): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $this->view($authorization);
        if ($payrollRun->status !== 'locked') {
            abort(422, 'The payroll register is available after the run is locked.');
        }
        $rows = $payrollRun->lines()->with('employee')->get()->map(fn (PayrollLine $line) => [
            $line->employee->employee_code,
            $line->employee->fullName(),
            number_format($line->gross_paise / 100, 2, '.', ''),
            number_format($line->deduction_paise / 100, 2, '.', ''),
            number_format($line->net_paise / 100, 2, '.', ''),
        ])->all();
        $binary = SpreadsheetFile::binary(['employee_code', 'name', 'gross', 'deductions', 'net'], $rows);

        return response()->streamDownload(fn () => print $binary, 'payroll-register.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function form16(Request $request, AuthorizationService $authorization, PayrollService $payroll): JsonResponse
    {
        $this->view($authorization);
        $data = $request->validate(['legal_entity_id' => ['required', 'integer'], 'financial_year' => ['required', 'integer']]);
        $entity = LegalEntity::query()->find($data['legal_entity_id']);
        if ($entity === null) {
            abort(404, 'The requested resource was not found.');
        }

        return ApiResponse::data($payroll->form16($entity, (int) $data['financial_year']));
    }

    public function settle(Request $request, AuthorizationService $authorization, PayrollService $payroll): JsonResponse
    {
        $this->process($request, $authorization);
        $data = $request->validate([
            'employee_id' => ['required', 'integer'],
            'last_working_on' => ['required', 'date'],
            'completed_years' => ['required', 'integer', 'min:0'],
            'encashable_days' => ['required', 'numeric', 'min:0'],
        ]);
        $employee = Employee::query()->find($data['employee_id']);
        if ($employee === null) {
            abort(404, 'The requested resource was not found.');
        }
        $settlement = $payroll->settle($employee, $data['last_working_on'], (int) $data['completed_years'], (float) $data['encashable_days']);

        return ApiResponse::data([
            'id' => $settlement->id,
            'gratuity' => number_format($settlement->gratuity_paise / 100, 2, '.', ''),
            'leave_encashment' => number_format($settlement->leave_encashment_paise / 100, 2, '.', ''),
            'net' => number_format($settlement->net_paise / 100, 2, '.', ''),
            'detail' => $settlement->detail,
        ], 201);
    }

    private function view(AuthorizationService $authorization): void
    {
        $authorization->authorizePermission(request()->user(), 'payroll.view');
    }

    private function process(Request $request, AuthorizationService $authorization): void
    {
        $authorization->authorizePermission($request->user(), 'payroll.process');
    }

    /**
     * @return array<string, mixed>
     */
    private function runPayload(PayrollRun $run, bool $detail): array
    {
        return [
            'id' => $run->id,
            'legal_entity_id' => $run->legal_entity_id,
            'year' => $run->year,
            'month' => $run->month,
            'status' => $run->status,
            'warnings' => $run->warnings,
            'lines' => $run->relationLoaded('lines') ? $run->lines->map(fn (PayrollLine $line) => [
                'id' => $line->id,
                'employee_id' => $line->employee_id,
                'employee_code' => $line->employee?->employee_code,
                'name' => $line->employee?->fullName(),
                'lop_days' => $line->lop_days,
                'gross' => number_format($line->gross_paise / 100, 2, '.', ''),
                'deductions' => number_format($line->deduction_paise / 100, 2, '.', ''),
                'net' => number_format($line->net_paise / 100, 2, '.', ''),
                'earnings' => $detail ? $line->earnings : null,
                'deduction_lines' => $detail ? $line->deductions : null,
            ])->all() : [],
        ];
    }
}
