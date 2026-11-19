<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Services;

use App\Models\Candidate;
use App\Models\CandidateResume;
use App\Models\DocumentSequence;
use App\Models\Employee;
use App\Models\Interview;
use App\Models\InterviewFeedback;
use App\Models\JobApplication;
use App\Models\JobOffer;
use App\Models\JobOpening;
use App\Models\JobRequisition;
use App\Models\LegalEntity;
use App\Models\OnboardingProfile;
use App\Models\OnboardingTask;
use App\Models\User;
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
    ) {}

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
        $offer->status = 'submitted';
        $offer->submitted_by = $actor->id;
        $offer->save();
        $this->audit->accessChange($actor, $this->context->get(), 'offer.submitted', ['offer_id' => $offer->id]);

        return $offer;
    }

    public function approveOffer(JobOffer $offer, User $actor): JobOffer
    {
        if ($offer->status !== 'submitted') {
            throw ValidationException::withMessages(['status' => ['The offer is not waiting for approval.']]);
        }
        if ($offer->submitted_by === $actor->id) {
            throw ValidationException::withMessages(['status' => ['The person who submitted the offer cannot approve it.']]);
        }
        $offer->status = 'approved';
        $offer->approved_by = $actor->id;
        $offer->save();
        $application = $offer->application;
        $application->stage = 'offer';
        $application->save();
        $this->audit->accessChange($actor, $this->context->get(), 'offer.approved', ['offer_id' => $offer->id]);

        return $offer;
    }

    public function acceptOffer(JobOffer $offer): JobOffer
    {
        if ($offer->status !== 'approved') {
            throw ValidationException::withMessages(['status' => ['An offer can be accepted after approval.']]);
        }
        $offer->status = 'accepted';
        $offer->save();

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
                'employee_code' => $this->nextEmployeeCode(),
                'given_name' => $candidate->given_name,
                'family_name' => $candidate->family_name,
                'email' => $candidate->email,
                'phone' => $candidate->phone,
                'legal_entity_id' => $entity->id,
                'joined_on' => $joinedOn,
                'designation_id' => null,
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
            $this->audit->accessChange($actor, $this->context->get(), 'candidate.converted', [
                'candidate_id' => $candidate->id,
                'employee_id' => $employee->id,
            ]);

            return $employee;
        });
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

    private function nextEmployeeCode(): string
    {
        $sequence = DocumentSequence::query()->firstOrCreate(
            ['series' => 'employee'],
            ['last_number' => 0],
        );
        $sequence->last_number++;
        $sequence->save();
        $code = 'EMP-'.str_pad((string) $sequence->last_number, 5, '0', STR_PAD_LEFT);
        if (Employee::query()->where('employee_code', $code)->exists()) {
            return $this->nextEmployeeCode();
        }

        return $code;
    }
}
