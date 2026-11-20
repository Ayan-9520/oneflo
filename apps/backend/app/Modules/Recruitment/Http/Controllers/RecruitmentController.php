<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\CandidateResume;
use App\Models\Interview;
use App\Models\Department;
use App\Models\Designation;
use App\Models\JobApplication;
use App\Models\JobOffer;
use App\Models\JobOpening;
use App\Models\JobRequisition;
use App\Models\LegalEntity;
use App\Modules\Recruitment\Services\RecruitmentService;
use App\Services\AuthorizationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RecruitmentController extends Controller
{
    public function requisitions(AuthorizationService $authorization): JsonResponse
    {
        $this->view($authorization);

        return ApiResponse::data(JobRequisition::query()->orderByDesc('id')->get()->map(fn (JobRequisition $row) => [
            'id' => $row->id,
            'legal_entity_id' => $row->legal_entity_id,
            'title' => $row->title,
            'description' => $row->description,
            'headcount' => $row->headcount,
            'status' => $row->status,
        ])->all());
    }

    public function storeRequisition(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $this->manage($request, $authorization);
        $data = $request->validate([
            'legal_entity_id' => ['required', 'integer'],
            'title' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string'],
            'headcount' => ['required', 'integer', 'min:1'],
            'department_id' => ['nullable', 'integer'],
            'designation_id' => ['nullable', 'integer'],
            'organization_id' => ['prohibited'],
        ]);
        if (LegalEntity::query()->whereKey($data['legal_entity_id'])->doesntExist()) {
            abort(404, 'The requested resource was not found.');
        }
        if (! empty($data['department_id']) && Department::query()->whereKey($data['department_id'])->where('status', 'active')->doesntExist()) {
            return ApiResponse::error('Choose a department in this business.', 422);
        }
        if (! empty($data['designation_id']) && Designation::query()->whereKey($data['designation_id'])->where('status', 'active')->doesntExist()) {
            return ApiResponse::error('Choose a designation in this business.', 422);
        }
        $row = JobRequisition::query()->create([...$data, 'status' => 'draft']);

        return ApiResponse::data(['id' => $row->id, 'status' => $row->status], 201);
    }

    public function showRequisition(JobRequisition $jobRequisition, AuthorizationService $authorization): JsonResponse
    {
        $this->view($authorization);

        return ApiResponse::data([
            'id' => $jobRequisition->id,
            'title' => $jobRequisition->title,
            'status' => $jobRequisition->status,
            'submitted_at' => $jobRequisition->submitted_at?->toIso8601String(),
            'history' => \App\Models\RequisitionApproval::query()->where('job_requisition_id', $jobRequisition->id)->orderBy('id')->get(['decision', 'comments', 'approver_user_id', 'acted_at']),
        ]);
    }

    public function submitRequisition(Request $request, JobRequisition $jobRequisition, AuthorizationService $authorization, RecruitmentService $recruitment): JsonResponse
    {
        $this->manage($request, $authorization);
        $row = $recruitment->submitRequisition($jobRequisition, $request->user());

        return ApiResponse::data(['id' => $row->id, 'status' => $row->status]);
    }

    public function approveRequisition(Request $request, JobRequisition $jobRequisition, AuthorizationService $authorization, RecruitmentService $recruitment): JsonResponse
    {
        $this->approveRequisitions($request, $authorization);
        $row = $recruitment->approveRequisition($jobRequisition, $request->user(), $request->input('comments'));

        return ApiResponse::data(['id' => $row->id, 'status' => $row->status]);
    }

    public function rejectRequisition(Request $request, JobRequisition $jobRequisition, AuthorizationService $authorization, RecruitmentService $recruitment): JsonResponse
    {
        $this->approveRequisitions($request, $authorization);
        $data = $request->validate(['comments' => ['required', 'string', 'max:2000']]);
        $row = $recruitment->rejectRequisition($jobRequisition, $request->user(), $data['comments']);

        return ApiResponse::data(['id' => $row->id, 'status' => $row->status]);
    }

    public function closeRequisition(Request $request, JobRequisition $jobRequisition, AuthorizationService $authorization, RecruitmentService $recruitment): JsonResponse
    {
        $this->manage($request, $authorization);
        $data = $request->validate(['status' => ['required', Rule::in(['closed', 'archived'])]]);
        $row = $recruitment->closeRequisition($jobRequisition, $request->user(), $data['status']);

        return ApiResponse::data(['id' => $row->id, 'status' => $row->status]);
    }

    public function openings(AuthorizationService $authorization): JsonResponse
    {
        $this->view($authorization);

        return ApiResponse::data(JobOpening::query()->orderByDesc('id')->get(['id', 'job_requisition_id', 'title', 'description', 'status', 'opened_on', 'slug', 'is_public'])->all());
    }

    public function storeOpening(Request $request, JobRequisition $jobRequisition, AuthorizationService $authorization, RecruitmentService $recruitment): JsonResponse
    {
        $this->manage($request, $authorization);
        $opening = $recruitment->openingFor($jobRequisition);

        return ApiResponse::data(['id' => $opening->id, 'status' => $opening->status], 201);
    }

    public function candidates(AuthorizationService $authorization): JsonResponse
    {
        $this->view($authorization);

        return ApiResponse::data(Candidate::query()->orderByDesc('id')->get()->map(fn (Candidate $candidate) => [
            'id' => $candidate->id,
            'full_name' => $candidate->fullName(),
            'email' => $candidate->email,
            'phone' => $candidate->phone,
            'source' => $candidate->source,
            'status' => $candidate->status,
            'is_test_data' => $candidate->is_test_data,
        ])->all());
    }

    public function storeCandidate(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $this->manage($request, $authorization);
        $data = $request->validate([
            'given_name' => ['required', 'string', 'max:80'],
            'family_name' => ['required', 'string', 'max:80'],
            'email' => ['nullable', 'email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'source' => ['nullable', 'string', 'max:80'],
            'organization_id' => ['prohibited'],
        ]);
        $candidate = Candidate::query()->create([...$data, 'status' => 'active']);

        return ApiResponse::data(['id' => $candidate->id], 201);
    }

    public function storeResume(Request $request, Candidate $candidate, AuthorizationService $authorization, RecruitmentService $recruitment): JsonResponse
    {
        $this->manage($request, $authorization);
        $request->validate([
            'resume' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'aadhaar_number' => ['prohibited'],
        ]);
        $resume = $recruitment->storeResume($candidate, $request->file('resume'));

        return ApiResponse::data(['id' => $resume->id, 'original_name' => $resume->original_name], 201);
    }

    public function resume(CandidateResume $candidateResume, AuthorizationService $authorization, RecruitmentService $recruitment): \Symfony\Component\HttpFoundation\Response
    {
        $this->view($authorization);

        return response($recruitment->resumeContents($candidateResume), 200, [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="'.$candidateResume->original_name.'"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function applications(AuthorizationService $authorization): JsonResponse
    {
        $this->view($authorization);

        $applications = JobApplication::query()->with(['candidate', 'opening'])->orderByDesc('id')->get();
        $ids = $applications->pluck('id');
        $interviews = Interview::query()->whereIn('job_application_id', $ids)->orderByDesc('scheduled_at')->get()->groupBy('job_application_id');
        $feedback = \App\Models\InterviewFeedback::query()->whereIn('interview_id', $interviews->flatten()->pluck('id'))->get()->keyBy('interview_id');
        $offers = JobOffer::query()->whereIn('job_application_id', $ids)->orderByDesc('id')->get()->groupBy('job_application_id');
        $resumes = CandidateResume::query()->whereIn('candidate_id', $applications->pluck('candidate_id'))->orderByDesc('id')->get()->groupBy('candidate_id');

        return ApiResponse::data($applications->map(function (JobApplication $application) use ($interviews, $feedback, $offers, $resumes): array {
            $candidate = $application->candidate;
            $offer = $offers->get($application->id)?->first();

            return [
                'id' => $application->id,
                'job_opening_id' => $application->job_opening_id,
                'job_title' => $application->opening?->title,
                'candidate_id' => $application->candidate_id,
                'candidate_name' => $candidate?->fullName(),
                'email' => $candidate?->email,
                'phone' => $candidate?->phone,
                'location' => $candidate?->location,
                'experience_years' => $candidate?->experience_years,
                'skills' => $candidate?->skills,
                'is_test_data' => (bool) $candidate?->is_test_data,
                'source' => $application->source ?? $candidate?->source,
                'expected_salary' => $application->expected_salary,
                'stage' => $application->stage,
                'applied_on' => $application->created_at?->toDateString(),
                'resume_id' => $resumes->get($application->candidate_id)?->first()?->id,
                'interviews' => ($interviews->get($application->id) ?? collect())->map(fn (Interview $interview) => [
                    'id' => $interview->id,
                    'scheduled_at' => $interview->scheduled_at instanceof \DateTimeInterface ? $interview->scheduled_at->format('Y-m-d H:i') : (string) $interview->scheduled_at,
                    'interviewer' => $interview->interviewer,
                    'mode' => $interview->mode,
                    'status' => $interview->status,
                    'rating' => $feedback->get($interview->id)?->rating,
                    'recommendation' => $feedback->get($interview->id)?->recommendation,
                    'comments' => $feedback->get($interview->id)?->comments,
                ])->values()->all(),
                'offer' => $offer ? [
                    'id' => $offer->id,
                    'designation' => $offer->designation,
                    'compensation_note' => $offer->compensation_note,
                    'status' => $offer->status,
                    'submitted_by' => $offer->submitted_by,
                    'decision_note' => $offer->decision_note,
                ] : null,
            ];
        })->all());
    }

    public function offerLetter(JobOffer $jobOffer, AuthorizationService $authorization, RecruitmentService $recruitment): \Symfony\Component\HttpFoundation\Response
    {
        $this->view($authorization);

        return response($recruitment->offerLetter($jobOffer), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="offer-letter-'.$jobOffer->id.'.pdf"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function storeApplication(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $this->manage($request, $authorization);
        $data = $request->validate([
            'job_opening_id' => ['required', 'integer'],
            'candidate_id' => ['required', 'integer'],
        ]);
        if (JobOpening::query()->whereKey($data['job_opening_id'])->doesntExist() || Candidate::query()->whereKey($data['candidate_id'])->doesntExist()) {
            abort(404, 'The requested resource was not found.');
        }
        $application = JobApplication::query()->create([...$data, 'stage' => 'applied']);

        return ApiResponse::data(['id' => $application->id, 'stage' => $application->stage], 201);
    }

    public function moveApplication(Request $request, JobApplication $jobApplication, AuthorizationService $authorization): JsonResponse
    {
        $this->manage($request, $authorization);
        $data = $request->validate(['stage' => ['required', Rule::in(['applied', 'screening', 'interview', 'offer', 'rejected'])]]);
        $jobApplication->stage = $data['stage'];
        $jobApplication->save();

        return ApiResponse::data(['id' => $jobApplication->id, 'stage' => $jobApplication->stage]);
    }

    public function storeInterview(Request $request, JobApplication $jobApplication, AuthorizationService $authorization, RecruitmentService $recruitment): JsonResponse
    {
        $this->manage($request, $authorization);
        $data = $request->validate([
            'scheduled_at' => ['required', 'date'],
            'interviewer' => ['required', 'string', 'max:120'],
            'mode' => ['required', Rule::in(['in_person', 'phone', 'video'])],
        ]);
        $interview = $recruitment->schedule($jobApplication, $data['scheduled_at'], $data['interviewer'], $data['mode']);

        return ApiResponse::data(['id' => $interview->id], 201);
    }

    public function storeFeedback(Request $request, Interview $interview, AuthorizationService $authorization, RecruitmentService $recruitment): JsonResponse
    {
        $this->manage($request, $authorization);
        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'comments' => ['required', 'string'],
            'recommendation' => ['required', Rule::in(['advance', 'hold', 'reject'])],
        ]);
        $feedback = $recruitment->feedback($interview, (int) $data['rating'], $data['comments'], $data['recommendation']);

        return ApiResponse::data(['id' => $feedback->id]);
    }

    public function storeOffer(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $this->manage($request, $authorization);
        $data = $request->validate([
            'job_application_id' => ['required', 'integer'],
            'legal_entity_id' => ['required', 'integer'],
            'designation' => ['required', 'string', 'max:120'],
            'compensation_note' => ['required', 'string', 'max:240'],
        ]);
        if (JobApplication::query()->whereKey($data['job_application_id'])->doesntExist() || LegalEntity::query()->whereKey($data['legal_entity_id'])->doesntExist()) {
            abort(404, 'The requested resource was not found.');
        }
        $offer = JobOffer::query()->create([...$data, 'status' => 'draft']);

        return ApiResponse::data(['id' => $offer->id, 'status' => $offer->status], 201);
    }

    public function submitOffer(Request $request, JobOffer $jobOffer, AuthorizationService $authorization, RecruitmentService $recruitment): JsonResponse
    {
        $this->manage($request, $authorization);
        $offer = $recruitment->submitOffer($jobOffer, $request->user());

        return ApiResponse::data(['id' => $offer->id, 'status' => $offer->status]);
    }

    public function offers(AuthorizationService $authorization): JsonResponse
    {
        $this->view($authorization);

        return ApiResponse::data(JobOffer::query()->orderByDesc('id')->get(['id', 'job_application_id', 'designation', 'status', 'submitted_by', 'approved_by'])->all());
    }

    public function showOffer(JobOffer $jobOffer, AuthorizationService $authorization): JsonResponse
    {
        $this->view($authorization);

        return ApiResponse::data([
            'id' => $jobOffer->id,
            'status' => $jobOffer->status,
            'designation' => $jobOffer->designation,
            'history' => \App\Models\OfferApproval::query()->where('job_offer_id', $jobOffer->id)->orderBy('id')->get(['decision', 'comments', 'approver_user_id', 'acted_at']),
        ]);
    }

    public function approveOffer(Request $request, JobOffer $jobOffer, AuthorizationService $authorization, RecruitmentService $recruitment): JsonResponse
    {
        $this->approveOffers($request, $authorization);
        $offer = $recruitment->approveOffer($jobOffer, $request->user());

        return ApiResponse::data(['id' => $offer->id, 'status' => $offer->status]);
    }

    public function rejectOffer(Request $request, JobOffer $jobOffer, AuthorizationService $authorization, RecruitmentService $recruitment): JsonResponse
    {
        $this->approveOffers($request, $authorization);
        $data = $request->validate(['comments' => ['required', 'string', 'max:2000']]);
        $offer = $recruitment->rejectOffer($jobOffer, $request->user(), $data['comments']);

        return ApiResponse::data(['id' => $offer->id, 'status' => $offer->status]);
    }

    public function sendOffer(Request $request, JobOffer $jobOffer, AuthorizationService $authorization, RecruitmentService $recruitment): JsonResponse
    {
        $this->manage($request, $authorization);
        $offer = $recruitment->sendOffer($jobOffer, $request->user());

        return ApiResponse::data(['id' => $offer->id, 'status' => $offer->status]);
    }

    public function acceptOffer(Request $request, JobOffer $jobOffer, AuthorizationService $authorization, RecruitmentService $recruitment): JsonResponse
    {
        $this->manage($request, $authorization);
        $before = \App\Models\Employee::query()->count();
        $offer = $recruitment->acceptOffer($jobOffer, $request->user());
        if (\App\Models\Employee::query()->count() !== $before) {
            abort(500, 'Accepting an offer must not create an employee.');
        }

        return ApiResponse::data(['id' => $offer->id, 'status' => $offer->status]);
    }

    public function declineOffer(Request $request, JobOffer $jobOffer, AuthorizationService $authorization, RecruitmentService $recruitment): JsonResponse
    {
        $this->manage($request, $authorization);
        $offer = $recruitment->declineOffer($jobOffer, $request->user());

        return ApiResponse::data(['id' => $offer->id, 'status' => $offer->status]);
    }

    public function convert(Request $request, JobOffer $jobOffer, AuthorizationService $authorization, RecruitmentService $recruitment): JsonResponse
    {
        $authorization->authorizePermission($request->user(), 'employees.create');
        $authorization->authorizePermission($request->user(), 'recruitment.joining.confirm');
        $this->manage($request, $authorization);
        $data = $request->validate(['joined_on' => ['required', 'date']]);
        $employee = $recruitment->convert($jobOffer, $request->user(), $data['joined_on']);

        return ApiResponse::data(['employee_id' => $employee->id, 'employee_code' => $employee->employee_code], 201);
    }

    private function view(AuthorizationService $authorization): void
    {
        $authorization->authorizePermission(request()->user(), 'recruitment.view');
    }

    private function manage(Request $request, AuthorizationService $authorization): void
    {
        $authorization->authorizePermission($request->user(), 'recruitment.manage');
    }

    private function approveRequisitions(Request $request, AuthorizationService $authorization): void
    {
        $authorization->authorizePermission($request->user(), 'recruitment.requisitions.approve');
    }

    private function approveOffers(Request $request, AuthorizationService $authorization): void
    {
        $authorization->authorizePermission($request->user(), 'recruitment.offers.approve');
    }
}
