<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Services;

use App\Models\Candidate;
use App\Models\CandidateResume;
use App\Models\Employee;
use App\Models\Interview;
use App\Models\InterviewFeedback;
use App\Models\JobApplication;
use App\Models\JobOffer;
use App\Models\JobOpening;
use App\Models\JobRequisition;
use App\Models\LegalEntity;
use App\Models\OfferApproval;
use App\Models\OnboardingProfile;
use App\Models\OnboardingTask;
use App\Models\RequisitionApproval;
use App\Models\User;
use App\Modules\Organization\Services\EmployeeCodeGenerator;
use App\Modules\Organization\Services\EmployeeLifecycle;
use App\Services\AuditLogger;
use App\Services\OrganizationContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

final class RecruitmentService
{
    public function __construct(
        private readonly OrganizationContext $context,
        private readonly AuditLogger $audit,
        private readonly EmployeeLifecycle $lifecycle,
        private readonly EmployeeCodeGenerator $codes,
    ) {}

    public function submitRequisition(JobRequisition $requisition, User $actor): JobRequisition
    {
        if ($requisition->status !== 'draft') {
            throw ValidationException::withMessages(['status' => ['Only a draft requisition can be submitted.']]);
        }
        $requisition->status = 'pending_approval';
        $requisition->submitted_by = $actor->id;
        $requisition->submitted_at = now();
        $requisition->save();
        $this->recordRequisition($requisition, $actor, 'submitted', null);
        $this->audit->accessChange($actor, $this->context->get(), 'requisition.submitted', ['requisition_id' => $requisition->id]);

        return $requisition;
    }

    public function approveRequisition(JobRequisition $requisition, User $actor, ?string $comments): JobRequisition
    {
        if ($requisition->status !== 'pending_approval') {
            throw ValidationException::withMessages(['status' => ['The requisition is not waiting for approval.']]);
        }

        return DB::transaction(function () use ($requisition, $actor, $comments): JobRequisition {
            $locked = JobRequisition::query()->whereKey($requisition->id)->lockForUpdate()->first();
            if ($locked->status !== 'pending_approval') {
                throw ValidationException::withMessages(['status' => ['This requisition was already decided.']]);
            }
            $locked->status = 'approved';
            $locked->save();
            $this->recordRequisition($locked, $actor, 'approved', $comments);
            $this->audit->accessChange($actor, $this->context->get(), 'requisition.approved', ['requisition_id' => $locked->id]);

            return $locked;
        });
    }

    public function rejectRequisition(JobRequisition $requisition, User $actor, string $comments): JobRequisition
    {
        if ($requisition->status !== 'pending_approval') {
            throw ValidationException::withMessages(['status' => ['The requisition is not waiting for approval.']]);
        }

        return DB::transaction(function () use ($requisition, $actor, $comments): JobRequisition {
            $locked = JobRequisition::query()->whereKey($requisition->id)->lockForUpdate()->first();
            if ($locked->status !== 'pending_approval') {
                throw ValidationException::withMessages(['status' => ['This requisition was already decided.']]);
            }
            $locked->status = 'rejected';
            $locked->save();
            $this->recordRequisition($locked, $actor, 'rejected', $comments);
            $this->audit->accessChange($actor, $this->context->get(), 'requisition.rejected', ['requisition_id' => $locked->id]);

            return $locked;
        });
    }

    public function closeRequisition(JobRequisition $requisition, User $actor, string $status): JobRequisition
    {
        if (! in_array($status, ['closed', 'archived'], true) || ! in_array($requisition->status, ['approved', 'closed'], true)) {
            throw ValidationException::withMessages(['status' => ['Only an approved requisition can be closed or archived.']]);
        }
        if ($status === 'closed' && $requisition->status !== 'approved') {
            throw ValidationException::withMessages(['status' => ['Only an approved requisition can be closed.']]);
        }
        $requisition->status = $status;
        $requisition->save();
        JobOpening::query()->where('job_requisition_id', $requisition->id)->where('status', 'open')->update(['status' => 'closed']);

        return $requisition;
    }

    public function schedule(JobApplication $application, string $scheduledAt, string $interviewer, string $mode): Interview
    {
        $application->stage = 'interview';
        $application->save();

        return Interview::query()->create([
            'job_application_id' => $application->id,
            'scheduled_at' => $scheduledAt,
            'interviewer' => $interviewer,
            'mode' => $mode,
            'status' => 'scheduled',
        ]);
    }

    public function feedback(Interview $interview, int $rating, string $comments, string $recommendation): InterviewFeedback
    {
        $interview->status = 'completed';
        $interview->save();

        return InterviewFeedback::query()->create([
            'interview_id' => $interview->id,
            'rating' => $rating,
            'comments' => $comments,
            'recommendation' => $recommendation,
        ]);
    }

    public function submitOffer(JobOffer $offer, User $actor): JobOffer
    {
        if ($offer->status !== 'draft') {
            throw ValidationException::withMessages(['status' => ['Only a draft offer can be submitted.']]);
        }
        $offer->status = 'pending_approval';
        $offer->submitted_by = $actor->id;
        $offer->save();
        $this->recordOffer($offer, $actor, 'submitted', null);
        $this->audit->accessChange($actor, $this->context->get(), 'offer.submitted', ['offer_id' => $offer->id]);

        return $offer;
    }

    public function approveOffer(JobOffer $offer, User $actor): JobOffer
    {
        if (! in_array($offer->status, ['submitted', 'pending_approval'], true)) {
            throw ValidationException::withMessages(['status' => ['The offer is not waiting for approval.']]);
        }
        if ($offer->submitted_by === $actor->id) {
            throw ValidationException::withMessages(['status' => ['The person who submitted the offer cannot approve it.']]);
        }

        return DB::transaction(function () use ($offer, $actor): JobOffer {
            $locked = JobOffer::query()->whereKey($offer->id)->lockForUpdate()->first();
            if (! in_array($locked->status, ['submitted', 'pending_approval'], true)) {
                throw ValidationException::withMessages(['status' => ['This offer was already decided.']]);
            }
            $locked->status = 'approved';
            $locked->approved_by = $actor->id;
            $locked->save();
            $application = $locked->application;
            $application->stage = 'offer';
            $application->save();
            $this->recordOffer($locked, $actor, 'approved', null);
            $this->audit->accessChange($actor, $this->context->get(), 'offer.approved', ['offer_id' => $locked->id]);

            return $locked;
        });
    }

    public function rejectOffer(JobOffer $offer, User $actor, string $comments): JobOffer
    {
        if (! in_array($offer->status, ['submitted', 'pending_approval'], true)) {
            throw ValidationException::withMessages(['status' => ['The offer is not waiting for approval.']]);
        }

        return DB::transaction(function () use ($offer, $actor, $comments): JobOffer {
            $locked = JobOffer::query()->whereKey($offer->id)->lockForUpdate()->first();
            if (! in_array($locked->status, ['submitted', 'pending_approval'], true)) {
                throw ValidationException::withMessages(['status' => ['This offer was already decided.']]);
            }
            $locked->status = 'rejected';
            $locked->decision_note = $comments;
            $locked->save();
            $this->recordOffer($locked, $actor, 'rejected', $comments);
            $this->audit->accessChange($actor, $this->context->get(), 'offer.rejected', ['offer_id' => $locked->id]);

            return $locked;
        });
    }

    public function sendOffer(JobOffer $offer, User $actor): JobOffer
    {
        if ($offer->status !== 'approved') {
            throw ValidationException::withMessages(['status' => ['Only an approved offer can be issued.']]);
        }
        DB::transaction(function () use ($offer, $actor): void {
            $offer->status = 'sent';
            $offer->sent_at = now();
            $offer->save();
            if ($this->issuedLetter($offer, 'offer_letter') === null) {
                $this->issueLetter($offer, 'offer_letter', $actor);
            }
        });
        $this->audit->accessChange($actor, $this->context->get(), 'offer.sent', ['offer_id' => $offer->id, 'email' => $this->emailOffer($offer)]);

        return $offer;
    }

    /**
     * Records the candidate's own answer to a sent offer. No employee is created here.
     */
    public function respondAsCandidate(JobOffer $offer, bool $accept): JobOffer
    {
        return DB::transaction(function () use ($offer, $accept): JobOffer {
            $locked = JobOffer::query()->withoutGlobalScopes()->whereKey($offer->id)->lockForUpdate()->first();
            if ($locked === null || $locked->status !== 'sent') {
                throw ValidationException::withMessages(['status' => ['This offer is no longer waiting for your answer.']]);
            }
            $locked->status = $accept ? 'accepted' : 'declined';
            $locked->setAttribute($accept ? 'accepted_at' : 'declined_at', now());
            $locked->decision_note = ($accept ? 'Accepted' : 'Declined').' by the candidate from the careers profile.';
            $locked->save();

            return $locked;
        });
    }

    /**
     * The issued offer letter once the offer is sent, otherwise an unnumbered draft preview.
     */
    public function offerLetter(JobOffer $offer): string
    {
        $issued = $this->issuedLetter($offer, 'offer_letter');
        if ($issued !== null) {
            return app(\App\Modules\Recruitment\Services\OnboardingService::class)->pdf($issued);
        }
        $organization = \App\Models\Organization::query()->find($offer->organization_id);
        $previous = $this->context->get();
        $this->context->set($organization);
        try {
            return app(LetterStudio::class)->render($this->entityFor($offer), 'offer_letter', $this->letterInput($offer));
        } finally {
            $previous === null ? $this->context->forget() : $this->context->set($previous);
        }
    }

    public function appointmentLetter(JobOffer $offer, User $actor, string $startsOn): \App\Models\IssuedDocument
    {
        if ($offer->status !== 'accepted') {
            throw ValidationException::withMessages(['status' => ['Issue the appointment letter after the candidate accepts the offer.']]);
        }

        return $this->issueLetter($offer, 'appointment_letter', $actor, ['joining_date' => $startsOn]);
    }

    public function issuedLetter(JobOffer $offer, string $type): ?\App\Models\IssuedDocument
    {
        $candidateId = JobApplication::query()->withoutGlobalScopes()->whereKey($offer->job_application_id)->value('candidate_id');

        return \App\Models\IssuedDocument::query()->withoutGlobalScopes()
            ->where('organization_id', $offer->organization_id)->where('candidate_id', $candidateId)
            ->where('document_type', $type)->whereNull('voided_at')->latest('id')->first();
    }

    /**
     * @param  array<string, string|null>  $extra
     */
    private function issueLetter(JobOffer $offer, string $type, User $actor, array $extra = [], ?int $employeeId = null): \App\Models\IssuedDocument
    {
        $candidate = Candidate::query()->withoutGlobalScopes()->findOrFail(JobApplication::query()->withoutGlobalScopes()->whereKey($offer->job_application_id)->value('candidate_id'));

        return app(LetterStudio::class)->issueFor($type, $this->entityFor($offer), [...$this->letterInput($offer), ...$extra], $candidate, $employeeId, $actor);
    }

    private function entityFor(JobOffer $offer): LegalEntity
    {
        $entity = LegalEntity::query()->withoutGlobalScopes()->find($offer->legal_entity_id);
        if ($entity === null) {
            throw ValidationException::withMessages(['legal_entity_id' => ['The legal employer for this offer was not found.']]);
        }

        return $entity;
    }

    /**
     * @return array<string, string|null>
     */
    private function letterInput(JobOffer $offer): array
    {
        $application = JobApplication::query()->withoutGlobalScopes()->find($offer->job_application_id);
        $candidate = $application ? Candidate::query()->withoutGlobalScopes()->find($application->candidate_id) : null;
        $opening = $application ? JobOpening::query()->withoutGlobalScopes()->find($application->job_opening_id) : null;
        $requisition = $opening ? JobRequisition::query()->withoutGlobalScopes()->find($opening->job_requisition_id) : null;
        $department = $requisition?->department_id ? \App\Models\Department::query()->withoutGlobalScopes()->whereKey($requisition->department_id)->value('name') : null;
        $digits = preg_replace('/[^0-9]/', '', (string) preg_replace('/\.\d+/', '', $offer->compensation_note));

        return [
            'given_name' => $candidate?->given_name ?? '',
            'family_name' => $candidate?->family_name ?? '',
            'email' => $candidate?->email ?? '',
            'phone' => $candidate?->phone ?? '',
            'address' => $candidate?->location ?? '',
            'designation' => $offer->designation,
            'department' => $department ?? $opening?->category ?? '',
            'ctc' => $digits !== '' && strlen($digits) <= 9 ? $digits : null,
            'letter_date' => now()->toDateString(),
        ];
    }

    private function emailOffer(JobOffer $offer): string
    {
        $application = $offer->application;
        $candidate = $application?->candidate;
        if ($candidate?->email === null || ! in_array(config('mail.default'), ['smtp', 'ses', 'postmark', 'resend'], true)) {
            return 'not_sent';
        }
        $company = $this->context->get()?->name ?? 'OneFlo';
        $profile = rtrim((string) config('app.frontend_url'), '/').'/careers/profile';
        try {
            \Illuminate\Support\Facades\Mail::raw(
                "Dear {$candidate->fullName()},\n\n{$company} is pleased to offer you the position of {$offer->designation}.\nCompensation: {$offer->compensation_note}\n\nThe offer letter is attached. Please sign in to your careers profile to accept or decline:\n{$profile}\n\nRegards,\nHR, {$company}",
                function ($message) use ($candidate, $offer, $company): void {
                    $message->to($candidate->email)
                        ->subject("Offer letter: {$offer->designation} at {$company}")
                        ->attachData($this->offerLetter($offer), 'offer-letter.pdf', ['mime' => 'application/pdf']);
                },
            );

            return 'sent';
        } catch (\Throwable) {
            return 'failed';
        }
    }

    public function acceptOffer(JobOffer $offer, User $actor): JobOffer
    {
        if (! in_array($offer->status, ['approved', 'sent'], true)) {
            throw ValidationException::withMessages(['status' => ['An offer can be accepted after it is approved.']]);
        }
        $offer->status = 'accepted';
        $offer->accepted_at = now();
        $offer->save();
        $this->audit->accessChange($actor, $this->context->get(), 'offer.accepted', ['offer_id' => $offer->id]);

        return $offer;
    }

    public function declineOffer(JobOffer $offer, User $actor): JobOffer
    {
        if (! in_array($offer->status, ['approved', 'sent'], true)) {
            throw ValidationException::withMessages(['status' => ['An offer can be declined after it is approved.']]);
        }
        $offer->status = 'declined';
        $offer->declined_at = now();
        $offer->save();
        $this->audit->accessChange($actor, $this->context->get(), 'offer.declined', ['offer_id' => $offer->id]);

        return $offer;
    }

    public function storeResume(Candidate $candidate, UploadedFile $file): CandidateResume
    {
        $path = $file->store('resumes/'.$this->context->id(), 'documents');

        return CandidateResume::query()->create([
            'candidate_id' => $candidate->id,
            'original_name' => $file->getClientOriginalName(),
            'path' => $path,
        ]);
    }

    public function resumeContents(CandidateResume $resume): string
    {
        return Storage::disk('documents')->get($resume->path);
    }

    public function convert(JobOffer $offer, User $actor, string $joinedOn): Employee
    {
        if ($offer->status !== 'accepted') {
            throw ValidationException::withMessages(['status' => ['Accept the approved offer before converting the candidate.']]);
        }

        return DB::transaction(function () use ($offer, $actor, $joinedOn): Employee {
            $offer->load('application.candidate');
            $candidate = $offer->application->candidate;
            $entity = LegalEntity::query()->find($offer->legal_entity_id);
            if ($entity === null) {
                throw ValidationException::withMessages(['legal_entity_id' => ['The legal employer was not found.']]);
            }
            $employee = $this->lifecycle->hire([
                'employee_code' => $this->codes->next($entity),
                'given_name' => $candidate->given_name,
                'family_name' => $candidate->family_name,
                'email' => $candidate->email,
                'phone' => $candidate->phone,
                'legal_entity_id' => $entity->id,
                'joined_on' => $joinedOn,
                ...$this->roleFor($offer),
                'note' => 'Converted from candidate '.$candidate->id,
            ], $actor);
            $candidate->status = 'hired';
            $candidate->save();
            $application = $offer->application;
            $application->stage = 'hired';
            $application->save();
            OnboardingProfile::query()->create([
                'employee_id' => $employee->id,
                'probation_status' => 'active',
                'identity_status' => 'pending',
            ]);
            foreach ([
                'identity' => 'Collect identity documents',
                'education' => 'Record education details',
                'bank' => 'Record bank details',
                'emergency' => 'Record an emergency contact',
                'policy' => 'Record policy acceptance',
                'account' => 'Create the employee account',
                'probation' => 'Set the probation date',
            ] as $code => $title) {
                OnboardingTask::query()->create([
                    'employee_id' => $employee->id,
                    'code' => $code,
                    'title' => $title,
                ]);
            }
            $this->issueLetter($offer, 'joining_letter', $actor, [
                'joining_date' => $joinedOn,
                'employee_code' => $employee->employee_code,
            ], $employee->id);
            $this->audit->accessChange($actor, $this->context->get(), 'candidate.converted', [
                'candidate_id' => $candidate->id,
                'employee_id' => $employee->id,
            ]);

            return $employee;
        });
    }

    /**
     * @return array{designation_id: int|null, department_id: int|null}
     */
    private function roleFor(JobOffer $offer): array
    {
        $opening = JobOpening::query()->find($offer->application?->job_opening_id);
        $requisition = $opening ? JobRequisition::query()->find($opening->job_requisition_id) : null;
        $designation = \App\Models\Designation::query()->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($offer->designation))])->first();
        if ($designation === null && $requisition?->designation_id !== null) {
            $designation = \App\Models\Designation::query()->find($requisition->designation_id);
        }

        return [
            'designation_id' => $designation?->id,
            'department_id' => $designation?->department_id ?? $requisition?->department_id,
        ];
    }

    public function openingFor(JobRequisition $requisition): JobOpening
    {
        if ($requisition->status !== 'approved') {
            throw ValidationException::withMessages(['status' => ['Approve the requisition before opening it.']]);
        }

        return JobOpening::query()->create([
            'job_requisition_id' => $requisition->id,
            'title' => $requisition->title,
            'description' => $requisition->description,
            'status' => 'open',
            'opened_on' => now()->toDateString(),
        ]);
    }

    private function recordRequisition(JobRequisition $requisition, User $actor, string $decision, ?string $comments): void
    {
        RequisitionApproval::query()->create([
            'job_requisition_id' => $requisition->id,
            'approver_user_id' => $actor->id,
            'decision' => $decision,
            'comments' => $comments,
            'acted_at' => now(),
        ]);
    }

    private function recordOffer(JobOffer $offer, User $actor, string $decision, ?string $comments): void
    {
        OfferApproval::query()->create([
            'job_offer_id' => $offer->id,
            'approver_user_id' => $actor->id,
            'decision' => $decision,
            'comments' => $comments,
            'acted_at' => now(),
        ]);
    }
}
