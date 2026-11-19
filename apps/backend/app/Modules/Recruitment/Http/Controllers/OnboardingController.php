<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\DocumentCategory;
use App\Models\Employee;
use App\Models\EmployeeBankAccount;
use App\Models\EmployeeEducation;
use App\Models\EmployeeFile;
use App\Models\EmergencyContact;
use App\Models\IssuedDocument;
use App\Models\LegalEntity;
use App\Models\OnboardingProfile;
use App\Models\OnboardingTask;
use App\Models\PolicyAcceptance;
use App\Modules\Recruitment\Services\OnboardingService;
use App\Services\AuthorizationService;
use App\Services\OrganizationContext;
use App\Support\ApiResponse;
use App\Support\DocumentTypes;
use App\Support\IdentityDocuments;
use App\Support\Permissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OnboardingController extends Controller
{
    public function show(Request $request, Employee $employee, AuthorizationService $authorization): JsonResponse
    {
        $this->visible($request, $employee, $authorization);
        $profile = OnboardingProfile::query()->where('employee_id', $employee->id)->first();

        return ApiResponse::data([
            'profile' => $profile === null ? null : [
                'probation_ends_on' => $profile->probation_ends_on?->toDateString(),
                'probation_status' => $profile->probation_status,
                'identity_status' => $profile->identity_status,
            ],
            'tasks' => OnboardingTask::query()->where('employee_id', $employee->id)->get()->map(fn (OnboardingTask $task) => [
                'code' => $task->code,
                'title' => $task->title,
                'completed' => $task->completed_at !== null,
            ])->all(),
            'education' => EmployeeEducation::query()->where('employee_id', $employee->id)->get(['qualification', 'institution', 'completed_year'])->all(),
            'bank_accounts' => EmployeeBankAccount::query()->where('employee_id', $employee->id)->get()->map(fn (EmployeeBankAccount $account) => [
                'bank_name' => $account->bank_name,
                'ifsc' => $account->ifsc,
                'account' => $account->maskedAccount(),
            ])->all(),
            'emergency_contacts' => EmergencyContact::query()->where('employee_id', $employee->id)->get(['name', 'relationship', 'phone'])->all(),
            'policies' => PolicyAcceptance::query()->where('employee_id', $employee->id)->get()->map(fn (PolicyAcceptance $row) => [
                'policy_name' => $row->policy_name,
                'accepted_on' => $row->accepted_on?->toDateString(),
            ])->all(),
        ]);
    }

    public function start(Request $request, Employee $employee, AuthorizationService $authorization, OnboardingService $onboarding): JsonResponse
    {
        $this->manage($request, $authorization);
        $data = $request->validate(['probation_ends_on' => ['nullable', 'date']]);
        $profile = $onboarding->start($employee, $data['probation_ends_on'] ?? null);

        return ApiResponse::data(['probation_status' => $profile->probation_status]);
    }

    public function storeEducation(Request $request, Employee $employee, AuthorizationService $authorization, OnboardingService $onboarding): JsonResponse
    {
        $this->manage($request, $authorization);
        $data = $request->validate([
            'qualification' => ['required', 'string', 'max:120'],
            'institution' => ['required', 'string', 'max:160'],
            'completed_year' => ['nullable', 'integer'],
        ]);
        $onboarding->addEducation($employee, $data['qualification'], $data['institution'], isset($data['completed_year']) ? (int) $data['completed_year'] : null);

        return ApiResponse::data(['recorded' => true], 201);
    }

    public function storeBank(Request $request, Employee $employee, AuthorizationService $authorization, OnboardingService $onboarding): JsonResponse
    {
        $this->manage($request, $authorization);
        $data = $request->validate([
            'bank_name' => ['required', 'string', 'max:120'],
            'ifsc' => ['required', 'string', 'max:11'],
            'account_number' => ['required', 'string', 'max:30'],
        ]);
        $account = $onboarding->addBank($employee, $data['bank_name'], $data['ifsc'], $data['account_number']);

        return ApiResponse::data(['account' => $account->maskedAccount()], 201);
    }

    public function storeContact(Request $request, Employee $employee, AuthorizationService $authorization, OnboardingService $onboarding): JsonResponse
    {
        $this->manage($request, $authorization);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'relationship' => ['required', 'string', 'max:80'],
            'phone' => ['required', 'string', 'max:30'],
        ]);
        $onboarding->addContact($employee, $data['name'], $data['relationship'], $data['phone']);

        return ApiResponse::data(['recorded' => true], 201);
    }

    public function storePolicy(Request $request, Employee $employee, AuthorizationService $authorization, OnboardingService $onboarding): JsonResponse
    {
        $this->manage($request, $authorization);
        $data = $request->validate([
            'policy_name' => ['required', 'string', 'max:160'],
            'accepted_on' => ['required', 'date'],
        ]);
        $onboarding->acceptPolicy($employee, $data['policy_name'], $data['accepted_on']);

        return ApiResponse::data(['recorded' => true], 201);
    }

    public function storeAccount(Request $request, Employee $employee, AuthorizationService $authorization, OnboardingService $onboarding): JsonResponse
    {
        $this->manage($request, $authorization);
        $authorization->authorizePermission($request->user(), Permissions::USERS_MANAGE);
        $onboarding->createAccount($employee, $request->user());

        return ApiResponse::data(['account_created' => true, 'password_returned' => false]);
    }

    public function probation(Request $request, Employee $employee, AuthorizationService $authorization, OnboardingService $onboarding): JsonResponse
    {
        $this->manage($request, $authorization);
        $data = $request->validate(['status' => ['required', Rule::in(['active', 'confirmed', 'extended'])]]);
        $profile = $onboarding->confirmProbation($employee, $data['status']);

        return ApiResponse::data(['probation_status' => $profile->probation_status]);
    }

    public function identity(Request $request, Employee $employee, AuthorizationService $authorization, OnboardingService $onboarding): JsonResponse
    {
        $authorization->authorizePermission($request->user(), Permissions::DOCUMENTS_IDENTITY);
        $data = $request->validate(['status' => ['required', Rule::in(['pending', 'verified', 'rejected'])]]);
        $profile = $onboarding->verifyIdentity($employee, $data['status']);

        return ApiResponse::data(['identity_status' => $profile->identity_status]);
    }

    public function categories(AuthorizationService $authorization): JsonResponse
    {
        $authorization->authorizePermission(request()->user(), Permissions::DOCUMENTS_VIEW);
        $custom = DocumentCategory::query()->orderBy('name')->get()->map(fn (DocumentCategory $category) => [
            'code' => $category->code,
            'name' => $category->name,
            'sensitive' => $category->sensitive,
            'requires_retention' => $category->requires_retention,
        ])->all();
        $builtin = [];
        foreach (IdentityDocuments::builtin() as $code => $definition) {
            $builtin[] = ['code' => $code, ...$definition];
        }

        return ApiResponse::data([...$builtin, ...$custom]);
    }

    public function storeCategory(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $authorization->authorizePermission($request->user(), Permissions::DOCUMENTS_GENERATE);
        $data = $request->validate([
            'code' => ['required', 'string', 'max:40'],
            'name' => ['required', 'string', 'max:120'],
            'sensitive' => ['required', 'boolean'],
            'requires_retention' => ['required', 'boolean'],
            'organization_id' => ['prohibited'],
        ]);
        if (array_key_exists($data['code'], IdentityDocuments::builtin())) {
            return ApiResponse::error('That document category already exists.', 422);
        }
        $category = DocumentCategory::query()->create($data);

        return ApiResponse::data(['id' => $category->id], 201);
    }

    public function files(Request $request, Employee $employee, AuthorizationService $authorization): JsonResponse
    {
        $this->visible($request, $employee, $authorization);
        $identity = $authorization->hasPermission($request->user(), Permissions::DOCUMENTS_IDENTITY, app(OrganizationContext::class)->get());

        return ApiResponse::data(EmployeeFile::query()->where('employee_id', $employee->id)->orderByDesc('id')->get()->map(function (EmployeeFile $file) use ($identity) {
            if ($file->sensitive && ! $identity) {
                return null;
            }

            return [
                'id' => $file->id,
                'category' => $file->category,
                'label' => $file->label,
                'sensitive' => $file->sensitive,
                'reference' => $file->reference_last4 === null ? null : 'XXXX'.$file->reference_last4,
                'retention_until' => $file->retention_until?->toDateString(),
            ];
        })->filter()->values()->all());
    }

    public function storeFile(Request $request, Employee $employee, AuthorizationService $authorization, OnboardingService $onboarding): JsonResponse
    {
        $authorization->authorizePermission($request->user(), Permissions::DOCUMENTS_GENERATE);
        $data = $request->validate([
            'category' => ['required', 'string', 'max:40'],
            'label' => ['required', 'string', 'max:160'],
            'reference_last4' => ['nullable', 'string', 'size:4'],
            'retention_until' => ['nullable', 'date'],
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'aadhaar_number' => ['prohibited'],
            'uid' => ['prohibited'],
        ]);
        if ($data['category'] === 'aadhaar' || ($onboarding->category($data['category'])['sensitive'] ?? false)) {
            $authorization->authorizePermission($request->user(), Permissions::DOCUMENTS_IDENTITY);
        }
        $file = $onboarding->storeFile($employee, $data['category'], $data['label'], $data['reference_last4'] ?? null, $data['retention_until'] ?? null, $request->file('file'), $request->user());

        return ApiResponse::data(['id' => $file->id, 'sensitive' => $file->sensitive], 201);
    }

    public function downloadFile(Request $request, EmployeeFile $employeeFile, AuthorizationService $authorization, OnboardingService $onboarding): \Symfony\Component\HttpFoundation\Response
    {
        $employeeFile->load('employee');
        $this->visible($request, $employeeFile->employee, $authorization);
        if ($employeeFile->sensitive) {
            $authorization->authorizePermission($request->user(), Permissions::DOCUMENTS_IDENTITY);
        }
        $contents = $onboarding->fileContents($employeeFile, $request->user());

        return response($contents, 200, [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="employee-file-'.$employeeFile->id.'"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function issued(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $authorization->authorizePermission($request->user(), Permissions::DOCUMENTS_VIEW);
        $query = IssuedDocument::query()->with(['candidate', 'employee'])->orderByDesc('id');
        $organization = app(\App\Services\OrganizationContext::class)->get();
        $seesAll = $authorization->hasPermission($request->user(), Permissions::EMPLOYEES_VIEW, $organization)
            || $authorization->hasPermission($request->user(), Permissions::DOCUMENTS_GENERATE, $organization);
        if (! $seesAll) {
            $ownId = \App\Models\Employee::query()->where('user_id', $request->user()->id)->value('id');
            $query->where('employee_id', $ownId ?? 0);
        }
        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->integer('employee_id'));
        }

        return ApiResponse::data($query->limit(200)->get()->map(function (IssuedDocument $document) {
            $source = $document->candidate ?? $document->employee;
            $given = trim((string) ($source?->given_name ?? ''));
            $family = trim((string) ($source?->family_name ?? ''));

            return [
            'id' => $document->id,
            'document_type' => $document->document_type,
            'person' => $given !== '' && $given === $family ? $given : trim($given.' '.$family),
            'number' => $document->number,
            'version' => $document->version,
            'issued_on' => $document->issued_on?->toDateString(),
            'oneflo_attribution' => $document->oneflo_attribution,
            'employee_id' => $document->employee_id,
            'voided_at' => $document->voided_at?->toIso8601String(),
            ];
        })->all());
    }

    public function issue(Request $request, AuthorizationService $authorization, OnboardingService $onboarding): JsonResponse
    {
        $authorization->authorizePermission($request->user(), Permissions::DOCUMENTS_GENERATE);
        $data = $request->validate([
            'legal_entity_id' => ['required', 'integer'],
            'document_type' => ['required', 'in:'.implode(',', DocumentTypes::keys())],
            'employee_id' => ['nullable', 'integer'],
            'candidate_id' => ['nullable', 'integer'],
        ]);
        $entity = LegalEntity::query()->find($data['legal_entity_id']);
        if ($entity === null) {
            abort(404, 'The requested resource was not found.');
        }
        $employee = isset($data['employee_id']) ? Employee::query()->find($data['employee_id']) : null;
        $candidate = isset($data['candidate_id']) ? Candidate::query()->find($data['candidate_id']) : null;
        if ($employee === null && $candidate === null) {
            abort(404, 'The requested resource was not found.');
        }
        $person = $employee !== null
            ? ['given_name' => $employee->given_name, 'family_name' => $employee->family_name, 'employee_code' => $employee->employee_code]
            : ['given_name' => $candidate->given_name, 'family_name' => $candidate->family_name, 'employee_code' => ''];
        $issued = $onboarding->issue($entity, $data['document_type'], $person, $employee, $candidate, $request->user());

        return ApiResponse::data([
            'id' => $issued->id,
            'number' => $issued->number,
            'version' => $issued->version,
            'oneflo_attribution' => $issued->oneflo_attribution,
            'verification_token' => $issued->plainVerificationToken,
        ], 201);
    }

    public function voidIssued(IssuedDocument $issuedDocument, AuthorizationService $authorization, OnboardingService $onboarding): JsonResponse
    {
        $authorization->authorizePermission(request()->user(), Permissions::DOCUMENTS_GENERATE);
        $issued = $onboarding->void($issuedDocument, request()->user());

        return ApiResponse::data([
            'id' => $issued->id,
            'number' => $issued->number,
            'version' => $issued->version,
            'voided_at' => $issued->voided_at?->toIso8601String(),
        ]);
    }

    public function downloadIssued(IssuedDocument $issuedDocument, AuthorizationService $authorization, OnboardingService $onboarding): \Symfony\Component\HttpFoundation\Response
    {
        $authorization->authorizeIssuedDocument(request()->user(), $issuedDocument);
        if ($issuedDocument->voided_at !== null) {
            return ApiResponse::error('This letter is cancelled.', 422);
        }

        return response($onboarding->pdf($issuedDocument), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$issuedDocument->number.'.pdf"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function manage(Request $request, AuthorizationService $authorization): void
    {
        $authorization->authorizePermission($request->user(), Permissions::ONBOARDING_MANAGE);
    }

    private function visible(Request $request, Employee $employee, AuthorizationService $authorization): void
    {
        $own = $employee->user_id !== null && $employee->user_id === $request->user()->id;
        $view = $authorization->hasPermission($request->user(), Permissions::DOCUMENTS_VIEW, app(OrganizationContext::class)->get())
            || $authorization->hasPermission($request->user(), Permissions::ONBOARDING_MANAGE, app(OrganizationContext::class)->get());
        if (! $own && ! $view) {
            abort(403, 'You are not allowed to perform this action.');
        }
        if (! $authorization->canViewEmployee($request->user(), $employee) && ! $own) {
            abort(404, 'The requested resource was not found.');
        }
    }
}
