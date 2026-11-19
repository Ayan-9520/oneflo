<?php

declare(strict_types=1);

namespace App\Modules\Documents\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AuthorizedSignatory;
use App\Models\IssuedDocument;
use App\Models\LegalEntity;
use App\Modules\Documents\Services\DocumentEngine;
use App\Services\AuthorizationService;
use App\Support\ApiResponse;
use App\Support\DocumentTypes;
use App\Support\Permissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentEngineController extends Controller
{
    public function verify(string $token): JsonResponse
    {
        $document = IssuedDocument::withoutGlobalScopes()->where('verification_hash', hash('sha256', $token))->first();
        if ($document === null) {
            abort(404, 'The requested resource was not found.');
        }
        $legalName = LegalEntity::withoutGlobalScopes()->whereKey($document->legal_entity_id)->value('legal_name');

        return ApiResponse::data([
            'number' => $document->number,
            'document_type' => $document->document_type,
            'legal_name' => $legalName,
            'issued_on' => $document->issued_on?->toDateString(),
            'version' => $document->version,
        ]);
    }

    public function versions(IssuedDocument $issuedDocument, AuthorizationService $authorization): JsonResponse
    {
        $authorization->authorizeIssuedDocument(request()->user(), $issuedDocument);
        $rows = IssuedDocument::query()
            ->where('document_type', $issuedDocument->document_type)
            ->where('employee_id', $issuedDocument->employee_id)
            ->when(
                $issuedDocument->candidate_id === null,
                fn ($query) => $query->whereNull('candidate_id'),
                fn ($query) => $query->where('candidate_id', $issuedDocument->candidate_id),
            )
            ->orderBy('version')
            ->get(['id', 'number', 'version', 'issued_on']);

        return ApiResponse::data($rows->map(fn (IssuedDocument $row) => [
            'id' => $row->id,
            'number' => $row->number,
            'version' => $row->version,
            'issued_on' => $row->issued_on?->toDateString(),
        ])->all());
    }

    public function docx(IssuedDocument $issuedDocument, AuthorizationService $authorization, DocumentEngine $engine): \Symfony\Component\HttpFoundation\Response
    {
        $authorization->authorizePermission(request()->user(), Permissions::DOCUMENTS_GENERATE);
        $authorization->authorizeIssuedDocument(request()->user(), $issuedDocument);
        if ($issuedDocument->voided_at !== null) {
            abort(422, 'This letter is cancelled.');
        }
        if (DocumentTypes::isOfficial($issuedDocument->document_type)) {
            abort(422, 'Official statutory forms stay in their prescribed PDF layout.');
        }
        $binary = $engine->docx($engine->signedLines($issuedDocument, $issuedDocument->lines ?? []));

        return response($binary, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'Content-Disposition' => 'attachment; filename="'.$issuedDocument->number.'.docx"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function sign(Request $request, IssuedDocument $issuedDocument, AuthorizationService $authorization, DocumentEngine $engine): JsonResponse
    {
        $authorization->authorizePermission($request->user(), Permissions::DOCUMENTS_GENERATE);
        $data = $request->validate(['signatory_id' => ['required', 'integer']]);
        $signatory = AuthorizedSignatory::query()->find($data['signatory_id']);
        if ($signatory === null) {
            abort(404, 'The requested resource was not found.');
        }
        $signature = $engine->sign($issuedDocument, $signatory, $request->user());

        return ApiResponse::data(['content_sha256' => $signature->content_sha256, 'signed_at' => $signature->signed_at?->toIso8601String()], 201);
    }
}
