<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\CandidateResume;
use App\Models\Interview;
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
            'organization_id' => ['prohibited'],
        ]);
        if (LegalEntity::query()->whereKey($data['legal_entity_id'])->doesntExist()) {
            abort(404, 'The requested resource was not found.');
        }
        $row = JobRequisition::query()->create([...$data, 'status' => 'draft']);

        return ApiResponse::data(['id' => $row->id, 'status' => $row->status], 201);
    }

    public function approveRequisition(Request $request, JobRequisition $jobRequisition, AuthorizationService $authorization): JsonResponse
    {
        $this->manage($request, $authorization);
        $jobRequisition->status = 'approved';
        $jobRequisition->save();

        return ApiResponse::data(['id' => $jobRequisition->id, 'status' => $jobRequisition->status]);
    }

    public function openings(AuthorizationService $authorization): JsonResponse
    {
        $this->view($authorization);

        return ApiResponse::data(JobOpening::query()->orderByDesc('id')->get(['id', 'job_requisition_id', 'title', 'description', 'status', 'opened_on'])->all());
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

        return ApiResponse::data(JobApplication::query()->with('candidate')->orderByDesc('id')->get()->map(fn (JobApplication $application) => [
            'id' => $application->id,
            'job_opening_id' => $application->job_opening_id,
            'candidate_id' => $application->candidate_id,
            'candidate_name' => $application->candidate?->fullName(),
            'stage' => $application->stage,
        ])->all());
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

    public function approveOffer(Request $request, JobOffer $jobOffer, AuthorizationService $authorization, RecruitmentService $recruitment): JsonResponse
    {
        $this->manage($request, $authorization);
        $offer = $recruitment->approveOffer($jobOffer, $request->user());

        return ApiResponse::data(['id' => $offer->id, 'status' => $offer->status]);
    }

    public function acceptOffer(Request $request, JobOffer $jobOffer, AuthorizationService $authorization, RecruitmentService $recruitment): JsonResponse
    {
        $this->manage($request, $authorization);
        $offer = $recruitment->acceptOffer($jobOffer);

        return ApiResponse::data(['id' => $offer->id, 'status' => $offer->status]);
    }

    public function convert(Request $request, JobOffer $jobOffer, AuthorizationService $authorization, RecruitmentService $recruitment): JsonResponse
    {
        $authorization->authorizePermission($request->user(), 'employees.create');
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
}
