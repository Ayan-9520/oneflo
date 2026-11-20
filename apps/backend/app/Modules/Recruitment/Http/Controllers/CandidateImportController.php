<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\CandidateImport;
use App\Modules\Recruitment\Services\CandidateImportService;
use App\Services\AuthorizationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class CandidateImportController extends Controller
{
    /** @var array<string, list<string>> */
    private const MIME_TYPES = [
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream'],
        'xls' => ['application/vnd.ms-excel', 'application/octet-stream', 'application/CDFV2', 'application/x-ole-storage'],
        'csv' => ['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel', 'text/x-csv'],
    ];

    public function template(Request $request, AuthorizationService $authorization, CandidateImportService $imports): Response
    {
        $this->manage($request, $authorization);

        return response($imports->template(), 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="oneflo-candidate-import-template.xlsx"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function store(Request $request, AuthorizationService $authorization, CandidateImportService $imports): JsonResponse
    {
        $this->manage($request, $authorization);
        $request->validate([
            'file' => ['required', 'file', 'max:5120'],
            'organization_id' => ['prohibited'],
        ]);
        $file = $request->file('file');
        $this->assertSpreadsheet($file);

        return ApiResponse::data($imports->payload($imports->preview($file, $request->user())), 201);
    }

    public function show(Request $request, CandidateImport $candidateImport, AuthorizationService $authorization, CandidateImportService $imports): JsonResponse
    {
        $this->manage($request, $authorization);

        return ApiResponse::data($imports->payload($candidateImport));
    }

    public function remap(Request $request, CandidateImport $candidateImport, AuthorizationService $authorization, CandidateImportService $imports): JsonResponse
    {
        $this->manage($request, $authorization);
        $data = $request->validate(['mapping' => ['present', 'array']]);

        return ApiResponse::data($imports->payload($imports->remap($candidateImport, $data['mapping'])));
    }

    public function confirm(Request $request, CandidateImport $candidateImport, AuthorizationService $authorization, CandidateImportService $imports): JsonResponse
    {
        $this->manage($request, $authorization);

        return ApiResponse::data($imports->payload($imports->confirm($candidateImport, $request->user())));
    }

    public function cancel(Request $request, CandidateImport $candidateImport, AuthorizationService $authorization, CandidateImportService $imports): JsonResponse
    {
        $this->manage($request, $authorization);

        return ApiResponse::data($imports->payload($imports->cancel($candidateImport, $request->user())));
    }

    public function errors(Request $request, CandidateImport $candidateImport, AuthorizationService $authorization, CandidateImportService $imports): Response
    {
        $this->manage($request, $authorization);

        return response($imports->errorReport($candidateImport), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="candidate-import-'.$candidateImport->id.'-errors.csv"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function assertSpreadsheet(UploadedFile $file): void
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $mime = (string) $file->getMimeType();
        if (! isset(self::MIME_TYPES[$extension]) || ! in_array($mime, self::MIME_TYPES[$extension], true)) {
            throw ValidationException::withMessages(['file' => ['Upload an Excel (.xlsx) or CSV file.']]);
        }
    }

    private function manage(Request $request, AuthorizationService $authorization): void
    {
        $authorization->authorizePermission($request->user(), 'recruitment.manage');
    }
}
