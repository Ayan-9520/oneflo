<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Services;

use App\Models\Candidate;
use App\Models\DocumentCategory;
use App\Models\DocumentSequence;
use App\Models\DocumentTemplate;
use App\Models\Employee;
use App\Models\EmployeeBankAccount;
use App\Models\EmployeeCompensation;
use App\Models\EmployeeEducation;
use App\Models\EmployeeFile;
use App\Models\EmergencyContact;
use App\Models\IssuedDocument;
use App\Models\LegalEntity;
use App\Models\OnboardingProfile;
use App\Models\OnboardingTask;
use App\Models\OrganizationAccess;
use App\Models\PolicyAcceptance;
use App\Models\Role;
use App\Models\User;
use App\Modules\Documents\Services\DocumentEngine;
use App\Modules\Documents\Services\DocumentRenderer;
use App\Services\AuditLogger;
use App\Services\OrganizationContext;
use App\Support\DocumentLetterBodies;
use App\Support\DocumentText;
use App\Support\DocumentTypes;
use App\Support\IdentityDocuments;
use App\Support\SimplePdf;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class OnboardingService
{
    public function __construct(
        private readonly OrganizationContext $context,
        private readonly AuditLogger $audit,
        private readonly DocumentRenderer $renderer,
        private readonly DocumentEngine $engine,
    ) {}

    /**
     * @return array{name: string, sensitive: bool, requires_retention: bool, custom: bool}
     */
    public function category(string $code): array
    {
        $builtin = IdentityDocuments::builtin()[$code] ?? null;
        if ($builtin !== null) {
            return [...$builtin, 'custom' => false];
        }
        $custom = DocumentCategory::query()->where('code', $code)->first();
        if ($custom === null) {
            throw ValidationException::withMessages(['category' => ['Choose a known document category.']]);
        }

        return [
            'name' => $custom->name,
            'sensitive' => $custom->sensitive,
            'requires_retention' => $custom->requires_retention,
            'custom' => true,
        ];
    }

    public function storeFile(Employee $employee, string $category, string $label, ?string $last4, ?string $retentionUntil, UploadedFile $file, User $actor): EmployeeFile
    {
        $definition = $this->category($category);
        if ($definition['requires_retention'] && ($retentionUntil === null || $retentionUntil === '')) {
            throw ValidationException::withMessages(['retention_until' => ['This identity document needs a retention date.']]);
        }
        if ($category === 'aadhaar' && $last4 !== null && strlen($last4) !== 4) {
            throw ValidationException::withMessages(['reference_last4' => ['Store only the last four characters, not the full identifier.']]);
        }
        $sensitive = $definition['sensitive'];
        $contents = file_get_contents($file->getRealPath());
        $stored = $sensitive ? Crypt::encryptString(base64_encode($contents === false ? '' : $contents)) : ($contents === false ? '' : $contents);
        $path = 'employee-files/'.$this->context->id().'/'.Str::uuid()->toString();
        Storage::disk('documents')->put($path, $stored);

        return EmployeeFile::query()->create([
            'employee_id' => $employee->id,
            'category' => $category,
            'label' => $label,
            'path' => $path,
            'sensitive' => $sensitive,
            'encrypted' => $sensitive,
            'reference_last4' => $last4,
            'retention_until' => $retentionUntil,
            'uploaded_by' => $actor->id,
        ]);
    }

    public function fileContents(EmployeeFile $file, User $actor): string
    {
        if ($file->retention_until !== null && $file->retention_until->isPast() && ! $file->retention_until->isToday()) {
            throw ValidationException::withMessages(['file' => ['This document is past its retention date and can no longer be downloaded.']]);
        }
        $stored = Storage::disk('documents')->get($file->path);
        $contents = $file->encrypted ? base64_decode(Crypt::decryptString($stored), true) : $stored;
        $this->audit->accessChange($actor, $this->context->get(), 'employee_file.downloaded', [
            'file_id' => $file->id,
            'category' => $file->category,
            'sensitive' => $file->sensitive,
        ]);

        return $contents === false ? '' : $contents;
    }

    public function createAccount(Employee $employee, User $actor): User
    {
        if ($employee->email === null || $employee->email === '') {
            throw ValidationException::withMessages(['email' => ['Add an email address before creating an account.']]);
        }
        if (User::query()->where('email', $employee->email)->exists()) {
            throw ValidationException::withMessages(['email' => ['An account with this email already exists. Assign access from the Access page.']]);
        }
        $user = User::query()->create([
            'name' => $employee->fullName(),
            'email' => $employee->email,
            'password' => Str::password(40),
        ]);
        $roleId = Role::query()->where('slug', 'employee')->value('id');
        OrganizationAccess::query()->create([
            'organization_id' => $this->context->id(),
            'user_id' => $user->id,
            'role_id' => $roleId,
        ]);
        $employee->user_id = $user->id;
        $employee->save();
        $this->completeTask($employee, 'account');
        $this->audit->accessChange($actor, $this->context->get(), 'employee.account_created', ['employee_id' => $employee->id, 'user_id' => $user->id]);

        return $user;
    }

    public function confirmProbation(Employee $employee, string $status): OnboardingProfile
    {
        $profile = OnboardingProfile::query()->where('employee_id', $employee->id)->first();
        if ($profile === null) {
            throw ValidationException::withMessages(['employee_id' => ['Start onboarding before updating probation.']]);
        }
        $profile->probation_status = $status;
        $profile->save();
        if ($status === 'confirmed') {
            $this->completeTask($employee, 'probation');
        }

        return $profile;
    }

    public function verifyIdentity(Employee $employee, string $status): OnboardingProfile
    {
        $profile = OnboardingProfile::query()->firstOrCreate(
            ['employee_id' => $employee->id],
            ['probation_status' => 'active', 'identity_status' => 'pending'],
        );
        $profile->identity_status = $status;
        $profile->save();
        if ($status === 'verified') {
            $this->completeTask($employee, 'identity');
        }

        return $profile;
    }

    /**
     * @param  array{given_name: string, family_name: string, employee_code?: string|null}  $person
     */
    public function issue(LegalEntity $entity, string $documentType, array $person, ?Employee $employee, ?Candidate $candidate, User $actor): IssuedDocument
    {
        if (! array_key_exists($documentType, DocumentTypes::catalog())) {
            throw ValidationException::withMessages(['document_type' => ['The document type is not recognized.']]);
        }

        return DB::transaction(function () use ($entity, $documentType, $person, $employee, $candidate, $actor): IssuedDocument {
            $number = $this->nextNumber($documentType);
            $version = IssuedDocument::query()
                ->where('document_type', $documentType)
                ->where('employee_id', $employee?->id)
                ->where('candidate_id', $candidate?->id)
                ->count() + 1;
            $template = DocumentTemplate::query()
                ->where('legal_entity_id', $entity->id)
                ->where('document_type', $documentType)
                ->first();
            $versionModel = $template?->currentVersion;
            $body = $versionModel->body ?? DocumentLetterBodies::for($documentType);
            $rendered = $this->renderer->render($entity, $documentType, [
                'body' => $body,
                'show_logo' => true,
                'show_signatory' => true,
                'show_stamp' => false,
            ], $this->letterValues($person, $employee, $number));
            $lines = DocumentText::lines($rendered['html']);
            if ($rendered['oneflo_attribution'] && ! in_array('Powered by OneFlo', $lines, true)) {
                $lines[] = 'Powered by OneFlo';
            }
            $issued = IssuedDocument::query()->create([
                'legal_entity_id' => $entity->id,
                'employee_id' => $employee?->id,
                'candidate_id' => $candidate?->id,
                'document_type' => $documentType,
                'number' => $number,
                'version' => $version,
                'issued_on' => now()->toDateString(),
                'html' => $rendered['html'],
                'lines' => $lines,
                'oneflo_attribution' => $rendered['oneflo_attribution'],
                'created_by' => $actor->id,
            ]);
            $this->engine->token($issued);
            $this->audit->accessChange($actor, $this->context->get(), 'document.issued', [
                'issued_document_id' => $issued->id,
                'document_type' => $documentType,
                'number' => $number,
                'version' => $version,
            ]);

            return $issued;
        });
    }

    public function void(IssuedDocument $document, User $actor): IssuedDocument
    {
        if ($document->voided_at !== null) {
            throw ValidationException::withMessages(['document' => ['This letter is already cancelled.']]);
        }

        $document->voided_at = now();
        $document->voided_by = $actor->id;
        $document->save();
        $this->audit->accessChange($actor, $this->context->get(), 'document.voided', [
            'issued_document_id' => $document->id,
            'document_type' => $document->document_type,
            'number' => $document->number,
            'version' => $document->version,
        ]);

        return $document;
    }

    public function pdf(IssuedDocument $document): string
    {
        if (is_string($document->html) && str_contains($document->html, 'data-letter-sheet')) {
            return app(\App\Support\LetterPdf::class)->binary($document->html);
        }
        $source = is_string($document->html) && $document->html !== ''
            ? DocumentText::lines($document->html)
            : ($document->lines ?? []);
        if ($document->oneflo_attribution && ! in_array('Powered by OneFlo', $source, true)) {
            $source[] = 'Powered by OneFlo';
        }

        return SimplePdf::text($this->engine->signedLines($document, $source));
    }

    public function addEducation(Employee $employee, string $qualification, string $institution, ?int $year): EmployeeEducation
    {
        $row = EmployeeEducation::query()->create([
            'employee_id' => $employee->id,
            'qualification' => $qualification,
            'institution' => $institution,
            'completed_year' => $year,
        ]);
        $this->completeTask($employee, 'education');

        return $row;
    }

    public function addBank(Employee $employee, string $bank, string $ifsc, string $account): EmployeeBankAccount
    {
        $row = EmployeeBankAccount::query()->create([
            'employee_id' => $employee->id,
            'bank_name' => $bank,
            'ifsc' => $ifsc,
            'account_number' => $account,
        ]);
        $this->completeTask($employee, 'bank');

        return $row;
    }

    public function addContact(Employee $employee, string $name, string $relationship, string $phone): EmergencyContact
    {
        $row = EmergencyContact::query()->create([
            'employee_id' => $employee->id,
            'name' => $name,
            'relationship' => $relationship,
            'phone' => $phone,
        ]);
        $this->completeTask($employee, 'emergency');

        return $row;
    }

    public function acceptPolicy(Employee $employee, string $policy, string $acceptedOn): PolicyAcceptance
    {
        $row = PolicyAcceptance::query()->create([
            'employee_id' => $employee->id,
            'policy_name' => $policy,
            'accepted_on' => $acceptedOn,
        ]);
        $this->completeTask($employee, 'policy');

        return $row;
    }

    public function start(Employee $employee, ?string $probationEndsOn): OnboardingProfile
    {
        $profile = OnboardingProfile::query()->firstOrCreate(
            ['employee_id' => $employee->id],
            ['probation_status' => 'active', 'identity_status' => 'pending', 'probation_ends_on' => $probationEndsOn],
        );
        if ($probationEndsOn !== null) {
            $profile->probation_ends_on = $probationEndsOn;
            $profile->save();
        }
        foreach ([
            'identity' => 'Collect identity documents',
            'education' => 'Record education details',
            'bank' => 'Record bank details',
            'emergency' => 'Record an emergency contact',
            'policy' => 'Record policy acceptance',
            'account' => 'Create the employee account',
            'probation' => 'Set the probation date',
        ] as $code => $title) {
            OnboardingTask::query()->firstOrCreate(
                ['employee_id' => $employee->id, 'code' => $code],
                ['title' => $title],
            );
        }
        $organization = \App\Models\Organization::query()->find($employee->organization_id);
        if ($organization instanceof \App\Models\Organization) {
            $employee->loadMissing('user');
            $notifier = app(\App\Modules\Notifications\NotificationService::class);
            $notifier->publish($organization, [$employee->user, ...$notifier->holders($organization, 'onboarding.manage')], \App\Modules\Notifications\NotificationEvents::DOCUMENT_PENDING, 'documents:'.$employee->id, 'Document pending', 'Identity and joining documents are still pending for '.$employee->fullName().'.');
        }

        return $profile;
    }

    private function completeTask(Employee $employee, string $code): void
    {
        OnboardingTask::query()->where('employee_id', $employee->id)->where('code', $code)->whereNull('completed_at')->update(['completed_at' => now()]);
    }

    /**
     * @param  array{given_name: string, family_name: string, employee_code?: string|null}  $person
     * @return array<string, string>
     */
    private function letterValues(array $person, ?Employee $employee, string $number): array
    {
        $employee?->loadMissing(['designation', 'department', 'manager']);
        $compensation = $employee === null
            ? null
            : EmployeeCompensation::query()->with('structure.lines.component')->where('employee_id', $employee->id)->first();
        $fixedTotal = 0.0;
        $variableTotal = 0.0;
        $hasPay = false;
        foreach ($compensation?->structure?->lines ?? [] as $line) {
            $annual = (float) $line->monthly_amount * 12;
            if ($line->component?->kind === 'earning') {
                $fixedTotal += $annual;
                $hasPay = true;
            }
            if ($line->component?->kind === 'variable') {
                $variableTotal += $annual;
                $hasPay = true;
            }
        }

        $fullName = trim($person['given_name'].' '.$person['family_name']);
        $joining = $employee?->joined_on;
        $joiningSentence = $joining === null
            ? 'A joining date is not recorded on this employee yet.'
            : 'Your expected joining date is on or before '.$joining->format('d-m-Y').'.';
        $compensationParagraph = $hasPay
            ? 'Your total compensation (CTC) for the year will be Rs. '.number_format($fixedTotal + $variableTotal, 2, '.', '').'. This includes a fixed salary component of Rs. '.number_format($fixedTotal, 2, '.', '').' per annum and a variable component of Rs. '.number_format($variableTotal, 2, '.', '').' per annum (performance-based).'
            : 'A salary structure is not recorded for this employee yet, so fixed pay, variable pay, and CTC are not stated in this letter.';

        return [
            'employee.full_name' => $fullName,
            'employee.given_name' => $person['given_name'],
            'employee.family_name' => $person['family_name'],
            'employee.employee_code' => $person['employee_code'] ?? '',
            'employee.designation' => $employee?->designation?->name ?? '',
            'employee.position_phrase' => ($employee?->designation?->name ?? '') !== ''
                ? 'for the position of '.$employee->designation->name
                : '. The saved record does not include a designation yet',
            'employee.department' => $employee?->department?->name ?? '',
            'employee.joining_date' => $joining?->toDateString() ?? '',
            'employee.joining_sentence' => $joiningSentence,
            'employee.compensation_paragraph' => $compensationParagraph,
            'employee.work_location' => $employee?->work_location ?? '',
            'employee.email' => $employee?->email ?? '',
            'employee.phone' => $employee?->phone ?? '',
            'employee.manager' => $employee?->manager?->fullName() ?? '',
            'employee.annual_fixed' => $hasPay ? number_format($fixedTotal, 2, '.', '') : '',
            'employee.annual_variable' => $hasPay ? number_format($variableTotal, 2, '.', '') : '',
            'employee.annual_ctc' => $hasPay ? number_format($fixedTotal + $variableTotal, 2, '.', '') : '',
            'document.number' => $number,
            'document.date' => now()->toDateString(),
        ];
    }

    private function nextNumber(string $documentType): string
    {
        $series = match ($documentType) {
            DocumentTypes::INTERNSHIP_OFFER => 'INTERN_OFFER',
            DocumentTypes::INTERNSHIP_COMPLETION => 'INTERN_DONE',
            DocumentTypes::TRANSFER_LETTER => 'TRANSFER',
            DocumentTypes::INTERVIEW_CALL_LETTER => 'INTERVIEW',
            DocumentTypes::AUTHORIZATION_LETTER => 'AUTHORIZE',
            DocumentTypes::EMPLOYMENT_VERIFICATION => 'EMP_VERIFY',
            default => strtoupper(substr($documentType, 0, 12)),
        };
        $sequence = DocumentSequence::query()->lockForUpdate()->firstOrCreate(
            ['series' => $series],
            ['last_number' => 0],
        );
        $sequence->last_number++;
        $sequence->save();

        return $series.'-'.now()->year.'-'.str_pad((string) $sequence->last_number, 4, '0', STR_PAD_LEFT);
    }
}
