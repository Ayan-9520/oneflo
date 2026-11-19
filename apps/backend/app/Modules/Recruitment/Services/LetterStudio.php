<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Services;

use App\Models\BrandIdentity;
use App\Models\Candidate;
use App\Models\CompanyProfile;
use App\Models\Designation;
use App\Models\DocumentSequence;
use App\Models\IssuedDocument;
use App\Models\LegalEntity;
use App\Models\User;
use App\Modules\Documents\Services\BrandingFileStore;
use App\Modules\Documents\Services\DocumentEngine;
use App\Modules\Organization\Services\EmployeeLifecycle;
use App\Services\AuditLogger;
use App\Services\OrganizationContext;
use App\Support\DocumentTypes;
use App\Support\LetterPdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Illuminate\Validation\ValidationException;

final class LetterStudio
{
    public function __construct(
        private readonly DocumentEngine $engine,
        private readonly EmployeeLifecycle $lifecycle,
        private readonly AuditLogger $audit,
        private readonly OrganizationContext $context,
        private readonly LetterPdf $pdf,
        private readonly BrandingFileStore $files,
    ) {}

    /**
     * @param  array<string, string|null>  $input
     * @return array{html: string, document: IssuedDocument|null, candidate: Candidate|null, employee_id: int|null}
     */
    public function issue(string $type, LegalEntity $entity, array $input, User $actor): array
    {
        if (! array_key_exists($type, DocumentTypes::catalog())) {
            throw ValidationException::withMessages(['document_type' => ['The document type is not recognized.']]);
        }
        $employeeId = null;
        $candidate = null;

        $document = DB::transaction(function () use ($entity, $type, $input, $actor, &$employeeId, &$candidate): IssuedDocument {
            $candidate = $this->candidate($type, $input);
            if ($type === DocumentTypes::JOINING_LETTER) {
                $designation = Designation::query()->firstOrCreate(['name' => $input['designation']]);
                $employee = $this->lifecycle->hire([
                    'employee_code' => $this->nextEmployeeCode(),
                    'given_name' => $input['given_name'],
                    'family_name' => $input['family_name'],
                    'email' => $input['email'] ?: null,
                    'phone' => $input['phone'] ?: null,
                    'legal_entity_id' => $entity->id,
                    'designation_id' => $designation->id,
                    'joined_on' => $input['joining_date'],
                    'work_location' => $input['address'] ?: null,
                    'employment_type' => 'permanent',
                ], $actor);
                $employeeId = $employee->id;
                $input['employee_code'] = $input['employee_code'] ?: $employee->employee_code;
                $candidate->status = 'joined';
                $candidate->save();
            } elseif ($candidate !== null && in_array($type, [DocumentTypes::OFFER_LETTER, DocumentTypes::APPOINTMENT_LETTER], true)) {
                $candidate->status = $type === DocumentTypes::OFFER_LETTER ? 'offered' : 'appointment';
                $candidate->save();
            }

            return $this->store($entity, $type, $this->html($entity, $type, $input, $actor), $employeeId, $candidate, $actor);
        });

        return ['html' => $document->html, 'document' => $document, 'candidate' => $candidate, 'employee_id' => $employeeId];
    }

    /**
     * @param  array<string, string|null>  $input
     */
    public function preview(LegalEntity $entity, string $type, array $input, User $actor): string
    {
        if (! array_key_exists($type, DocumentTypes::catalog())) {
            throw ValidationException::withMessages(['document_type' => ['The document type is not recognized.']]);
        }

        return $this->pdf->binary($this->html($entity, $type, $input, $actor));
    }

    /**
     * @param  array<string, string|null>  $input
     */
    private function html(LegalEntity $entity, string $type, array $input, User $actor): string
    {
        $profile = CompanyProfile::query()->with(['signatories', 'offices'])->where('legal_entity_id', $entity->id)->first();
        $signatory = $profile?->signatories->firstWhere('is_primary', true) ?? $profile?->signatories->first();
        $company = $profile?->legal_company_name ?? $entity->legal_name;
        $given = trim((string) ($input['given_name'] ?? ''));
        $family = trim((string) ($input['family_name'] ?? ''));
        $person = $given !== '' && $given === $family ? $given : trim($given.' '.$family);
        $joining = $this->displayDate($input['joining_date'] ?? null);
        $end = $this->displayDate($input['end_date'] ?? null);
        $role = trim((string) ($input['designation'] ?? ''));
        $code = trim((string) ($input['employee_code'] ?? ''));
        $variable = $this->money($input['variable'] ?? null);
        $ctc = $this->money($input['ctc'] ?? null);
        $compensationParts = array_values(array_filter([
            $ctc !== '' ? 'Your total compensation (CTC) for the year will be Rs. '.$ctc.'.' : '',
            $variable !== '' ? 'The variable amount is Rs. '.$variable.' per annum and depends on performance.' : '',
        ]));
        $compensation = $compensationParts === [] ? null : implode(' ', $compensationParts);
        if ($type === DocumentTypes::OFFER_LETTER) {
            $compensation = implode(' ', [
                $ctc !== '' ? 'Your total compensation (CTC) for the year will be Rs. '.$ctc.'.' : 'Your total compensation (CTC) is the yearly amount recorded for this offer.',
                $variable !== '' ? 'The variable amount is Rs. '.$variable.' per annum and depends on performance.' : 'A variable amount, where it applies, depends on performance.',
            ]);
        }
        $identity = $entity->brand_id === null ? null : BrandIdentity::query()->where('brand_id', $entity->brand_id)->first();
        $sections = $this->sections($type, $company, $person, $role, $joining, $end, $compensation, $ctc, $code);
        if (trim((string) ($input['department'] ?? '')) !== '') {
            $sections['paragraphs'][0] .= ' Department: '.$input['department'].'.';
        }
        $subject = DocumentTypes::catalog()[$type]['label'];
        $letterDate = $this->displayDate($input['letter_date'] ?? null);

        return View::make('documents.letter-sheet', [
            'title' => $subject,
            'company' => $company,
            'mark' => $this->mark($company),
            'logo' => $this->files->dataUri($identity?->logo_path),
            'primary' => $this->hex($identity?->primary_color, '#3a35e0'),
            'secondary' => $this->hex($identity?->secondary_color, '#6d38d6'),
            'address' => ($input['company_address'] ?? '') !== '' ? $input['company_address'] : $this->companyAddress($profile),
            'phone' => ($input['company_phone'] ?? '') !== '' ? $input['company_phone'] : ($profile?->contact_phone ?? ''),
            'website' => ($input['company_website'] ?? '') !== '' ? $input['company_website'] : ($profile?->website ?? ''),
            'email' => ($input['company_email'] ?? '') !== '' ? $input['company_email'] : ($profile?->contact_email ?? ''),
            'date' => $letterDate !== '' ? $letterDate : now()->format('d-m-Y'),
            'person' => $person,
            'personAddress' => $input['address'] ?? '',
            'personEmail' => $input['email'] ?? '',
            'personPhone' => $input['phone'] ?? '',
            'department' => $input['department'] ?? '',
            'role' => $role,
            'subject' => $subject,
            'paragraphs' => $sections['paragraphs'],
            'compensation' => $sections['compensation'],
            'joining' => $sections['joining'],
            'requirements' => $sections['requirements'],
            'documents' => $sections['documents'],
            'signatory' => $signatory?->name ?: $actor->name,
            'signatoryTitle' => $signatory?->designation ?? '',
            'signatoryContact' => trim((string) ($profile?->contact_email ?: $profile?->contact_phone)),
            'footer' => DocumentTypes::isOfficial($type) ? null : 'Powered by OneFlo',
            'subjectLine' => $type === DocumentTypes::OFFER_LETTER ? 'Offer of employment' : $subject,
            'compensationTitle' => $type === DocumentTypes::OFFER_LETTER ? 'Your compensation package' : (in_array($type, [DocumentTypes::APPOINTMENT_LETTER, DocumentTypes::JOINING_LETTER, DocumentTypes::CONFIRMATION_LETTER], true) ? 'Salary structure' : 'Compensation'),
            'datesTitle' => $type === DocumentTypes::OFFER_LETTER ? 'Joining date' : 'Dates',
        ])->render();
    }

    /**
     * @return array{paragraphs: list<string>, compensation: string|null, joining: string|null, requirements: string|null, documents: string|null}
     */
    private function sections(string $type, string $company, string $person, string $role, string $joining, string $end, ?string $compensation, string $ctc, string $code): array
    {
        $who = $person !== '' ? $person : 'the person named on this letter';
        $rolePhrase = $role !== '' ? $role : 'the role entered on this letter';
        $start = $joining !== '' ? $joining : 'not entered';
        $finish = $end !== '' ? $end : 'not entered';
        $codeSentence = $code !== '' ? ' Employee code: '.$code.'.' : '';
        $blank = ['compensation' => null, 'joining' => null, 'requirements' => null, 'documents' => null];

        $offerPay = [
            'compensation' => $compensation,
            'joining' => $joining === '' ? 'The joining date is agreed before you join. If you do not join by that date, this offer can be withdrawn.' : 'Your expected joining date is on or before '.$joining.'. If you do not join by this date, this offer can be withdrawn.',
            'requirements' => 'Before joining, you are required to sign a non-disclosure agreement.',
            'documents' => 'Please bring copies of your educational certificates when you join.',
        ];

        return match ($type) {
            DocumentTypes::OFFER_LETTER => [
                'paragraphs' => [
                    $company.' offers '.$who.' the position of '.$rolePhrase.'.'.$codeSentence,
                    'Any other benefit follows the policy for this role.',
                ],
                ...$offerPay,
            ],
            DocumentTypes::APPOINTMENT_LETTER => [
                'paragraphs' => [
                    $company.' appoints '.$who.' to the position of '.$rolePhrase.'.'.$codeSentence,
                    'This appointment is issued after the candidate accepts the offer. The salary structure is the pay entered below.',
                ],
                'compensation' => $compensation,
                'joining' => $joining === '' ? null : 'Employment starts on '.$start.'.',
                'requirements' => null,
                'documents' => null,
            ],
            DocumentTypes::JOINING_LETTER => [
                'paragraphs' => [
                    $company.' records that '.$who.' is joining as '.$rolePhrase.' after accepting the offer.'.$codeSentence,
                    'This joining step creates the employee record.',
                ],
                'compensation' => $compensation,
                'joining' => $joining === '' ? null : 'The joining date on this letter is '.$start.'.',
                'requirements' => null,
                'documents' => 'Please bring copies of your educational certificates when you join.',
            ],
            DocumentTypes::INTERNSHIP_OFFER => [
                'paragraphs' => [
                    $company.' offers '.$who.' an internship for '.$rolePhrase.'.'.$codeSentence,
                    'This internship is not a confirmation of employment after it ends.',
                ],
                'compensation' => 'The internship stipend entered on this letter is Rs. '.($ctc === '' ? 'not entered' : $ctc).'.',
                'joining' => 'The internship start date is '.$start.' and the end date is '.$finish.'.',
                'requirements' => null,
                'documents' => null,
            ],
            DocumentTypes::EXPERIENCE_CERTIFICATE => [
                'paragraphs' => [
                    $company.' confirms that '.$who.' was associated with the organisation as '.$rolePhrase.'.'.$codeSentence,
                    'The saved record runs from '.$start.' to '.$finish.'.',
                ],
                ...$blank,
            ],
            DocumentTypes::RELIEVING_LETTER => [
                'paragraphs' => [
                    $company.' records that '.$who.', '.$rolePhrase.', is relieved from employment.'.$codeSentence,
                    'The employment on this letter runs from '.$start.'. The last working date is '.$finish.'.',
                ],
                ...$blank,
            ],
            DocumentTypes::INTERVIEW_CALL_LETTER => [
                'paragraphs' => [
                    $company.' invites '.$who.' to an interview for '.$rolePhrase.'.',
                    'The interview date, time, and place are not entered on this letter. Share those separately once they are fixed.',
                ],
                ...$blank,
            ],
            DocumentTypes::WARNING_LETTER => [
                'paragraphs' => [
                    $company.' issues this warning to '.$who.' for the role of '.$rolePhrase.'.'.$codeSentence,
                    'The specific incident is not entered here. Add it in a saved template before this letter is used for a real warning.',
                ],
                ...$blank,
            ],
            DocumentTypes::CONFIRMATION_LETTER => [
                'paragraphs' => [
                    $company.' confirms the employment of '.$who.' as '.$rolePhrase.' after the offer was accepted and the person joined.'.$codeSentence,
                    'The salary structure below is the pay entered for this person.',
                ],
                'compensation' => $compensation,
                'joining' => $joining === '' ? null : 'The joining date on this letter is '.$start.'.',
                'requirements' => null,
                'documents' => null,
            ],
            default => [
                'paragraphs' => [
                    $company.' issues this '.strtolower(DocumentTypes::catalog()[$type]['label']).' to '.$who.' for the role of '.$rolePhrase.'.'.$codeSentence,
                    'It uses only the details entered on this form. Details that were left blank are not invented.',
                ],
                'compensation' => in_array($type, [DocumentTypes::INCREMENT_LETTER, DocumentTypes::SALARY_CERTIFICATE, DocumentTypes::EMPLOYMENT_AGREEMENT, DocumentTypes::PROMOTION_LETTER, DocumentTypes::EMPLOYMENT_VERIFICATION], true) ? $compensation : null,
                'joining' => in_array($type, [DocumentTypes::CONFIRMATION_LETTER, DocumentTypes::INTERNSHIP_COMPLETION, DocumentTypes::RESIGNATION_ACCEPTANCE, DocumentTypes::TRANSFER_LETTER], true) ? 'The date entered on this letter is '.$start.($end !== '' ? ', and the closing date is '.$finish.'.' : '.') : null,
                'requirements' => null,
                'documents' => null,
            ],
        };
    }

    /**
     * @param  array<string, string|null>  $input
     */
    private function candidate(string $type, array $input): ?Candidate
    {
        if ($type !== DocumentTypes::OFFER_LETTER) {
            $candidate = Candidate::query()->find($input['candidate_id'] ?? 0);
            if (in_array($type, [DocumentTypes::APPOINTMENT_LETTER, DocumentTypes::JOINING_LETTER, DocumentTypes::CONFIRMATION_LETTER], true) && $candidate === null) {
                throw ValidationException::withMessages(['candidate_id' => ['Issue the offer letter before the next step.']]);
            }
            if ($type === DocumentTypes::CONFIRMATION_LETTER && $candidate?->status !== 'joined') {
                throw ValidationException::withMessages(['document_type' => ['Confirmation is issued after the candidate accepts the offer and the joining letter is issued.']]);
            }
            if ($type === DocumentTypes::APPOINTMENT_LETTER && $candidate?->status === 'joined') {
                throw ValidationException::withMessages(['document_type' => ['This candidate has already joined.']]);
            }

            return $candidate;
        }

        return Candidate::query()->create([
            'given_name' => $input['given_name'],
            'family_name' => $input['family_name'],
            'email' => $input['email'] ?: null,
            'phone' => $input['phone'] ?: null,
            'source' => 'letter',
            'status' => 'offered',
        ]);
    }

    private function store(LegalEntity $entity, string $type, string $html, ?int $employeeId, ?Candidate $candidate, User $actor): IssuedDocument
    {
        $number = $this->nextNumber($type);
        $versionQuery = IssuedDocument::query()->where('document_type', $type);
        if ($candidate !== null) {
            $versionQuery->where('candidate_id', $candidate->id);
        } elseif ($employeeId !== null) {
            $versionQuery->where('employee_id', $employeeId);
        } else {
            $versionQuery->whereNull('candidate_id')->whereNull('employee_id');
        }
        $version = $versionQuery->count() + 1;
        $issued = IssuedDocument::query()->create([
            'legal_entity_id' => $entity->id,
            'employee_id' => $employeeId,
            'candidate_id' => $candidate?->id,
            'document_type' => $type,
            'number' => $number,
            'version' => $version,
            'issued_on' => now()->toDateString(),
            'html' => $html,
            'lines' => [DocumentTypes::catalog()[$type]['label'], $number],
            'oneflo_attribution' => ! DocumentTypes::isOfficial($type),
            'created_by' => $actor->id,
        ]);
        $this->engine->token($issued);
        $this->audit->accessChange($actor, $this->context->get(), 'document.issued', [
            'issued_document_id' => $issued->id,
            'document_type' => $type,
            'number' => $number,
            'version' => $version,
        ]);

        return $issued;
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
        $sequence = DocumentSequence::query()->lockForUpdate()->firstOrCreate(['series' => $series], ['last_number' => 0]);
        $sequence->last_number++;
        $sequence->save();

        return $series.'-'.now()->year.'-'.str_pad((string) $sequence->last_number, 4, '0', STR_PAD_LEFT);
    }

    private function nextEmployeeCode(): string
    {
        $next = \App\Models\Employee::query()->count() + 1;

        return 'RD-'.str_pad((string) $next, 3, '0', STR_PAD_LEFT);
    }

    private function money(?string $value): string
    {
        if ($value === null || trim($value) === '') {
            return '';
        }

        return number_format((float) $value, 2, '.', '');
    }

    private function companyAddress(?CompanyProfile $profile): string
    {
        if ($profile === null) {
            return '';
        }
        if (trim((string) $profile->registered_address) !== '') {
            return (string) $profile->registered_address;
        }
        $office = $profile->offices->first();
        if ($office === null) {
            return '';
        }

        return implode(', ', array_values(array_filter([
            $office->line_1,
            $office->line_2,
            $office->city,
            $office->state,
            $office->postal_code,
        ])));
    }

    private function displayDate(?string $value): string
    {
        if ($value === null || trim($value) === '') {
            return '';
        }

        $time = strtotime($value);

        return $time === false ? '' : date('d-m-Y', $time);
    }

    private function hex(?string $value, string $fallback): string
    {
        return is_string($value) && preg_match('/^#[0-9A-Fa-f]{6}$/', $value) === 1 ? $value : $fallback;
    }

    private function mark(string $company): string
    {
        $parts = array_values(array_filter(preg_split('/\s+/', trim($company)) ?: []));
        if ($parts === []) {
            return 'CO';
        }
        if (count($parts) === 1) {
            return mb_strtoupper(mb_substr($parts[0], 0, 2));
        }
        $letters = '';
        foreach ($parts as $part) {
            $letters .= mb_strtoupper(mb_substr($part, 0, 1));
            if (mb_strlen($letters) >= 3) {
                break;
            }
        }

        return $letters;
    }
}
