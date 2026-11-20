<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\LegalEntity;
use App\Modules\Recruitment\Services\LetterStudio;
use App\Services\AuthorizationService;
use App\Support\ApiResponse;
use App\Support\DocumentTypes;
use App\Support\Permissions;
use Illuminate\Http\Request;

class LetterStudioController extends Controller
{
    public function preview(Request $request, AuthorizationService $authorization, LetterStudio $studio): \Symfony\Component\HttpFoundation\Response
    {
        $authorization->authorizePermission($request->user(), Permissions::DOCUMENTS_GENERATE);
        $input = $this->input($request, requireCandidate: false);
        $entity = $this->entity((int) $input['legal_entity_id']);

        return response($studio->preview($entity, (string) $input['document_type'], $input, $request->user()), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="letter-preview.pdf"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function store(Request $request, AuthorizationService $authorization, LetterStudio $studio): \Illuminate\Http\JsonResponse
    {
        $authorization->authorizePermission($request->user(), Permissions::DOCUMENTS_GENERATE);
        $input = $this->input($request, requireCandidate: in_array($request->input('document_type'), [DocumentTypes::APPOINTMENT_LETTER, DocumentTypes::JOINING_LETTER, DocumentTypes::CONFIRMATION_LETTER], true));
        $entity = $this->entity((int) $input['legal_entity_id']);
        $result = $studio->issue((string) $input['document_type'], $entity, $input, $request->user());

        return ApiResponse::data([
            'candidate_id' => $result['candidate']?->id,
            'employee_id' => $result['employee_id'],
            'document_id' => $result['document']?->id,
            'number' => $result['document']?->number,
            'document_type' => $result['document']?->document_type,
        ], 201);
    }

    /**
     * @return array<string, string|null>
     */
    private function input(Request $request, bool $requireCandidate): array
    {
        $hire = in_array($request->input('document_type'), [DocumentTypes::OFFER_LETTER, DocumentTypes::APPOINTMENT_LETTER, DocumentTypes::JOINING_LETTER], true);
        $request->merge([
            'ctc' => $this->money($request->input('ctc')),
            'fixed' => $this->money($request->input('fixed')),
            'variable' => $this->money($request->input('variable')),
        ]);
        $data = $request->validate([
            'document_type' => ['required', 'in:'.implode(',', DocumentTypes::keys())],
            'legal_entity_id' => ['required', 'integer'],
            'candidate_id' => [$requireCandidate ? 'required' : 'nullable', 'integer'],
            'given_name' => ['required', 'string', 'max:80'],
            'family_name' => ['required', 'string', 'max:80'],
            'email' => ['nullable', 'email', 'max:160'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:240'],
            'department' => ['nullable', 'string', 'max:120'],
            'company_address' => ['nullable', 'string', 'max:240'],
            'company_phone' => ['nullable', 'string', 'max:40'],
            'company_website' => ['nullable', 'string', 'max:160'],
            'company_email' => ['nullable', 'email', 'max:160'],
            'letter_date' => ['nullable', 'date'],
            'signatory_name' => ['nullable', 'string', 'max:120'],
            'signatory_title' => ['nullable', 'string', 'max:120'],
            'signatory_contact' => ['nullable', 'string', 'max:160'],
            'designation' => ['required', 'string', 'max:120'],
            'employee_code' => ['nullable', 'string', 'max:40'],
            'joining_date' => [$hire ? 'required' : 'nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'ctc' => ['nullable', 'numeric', 'min:0'],
            'fixed' => ['nullable', 'numeric', 'min:0'],
            'variable' => ['nullable', 'numeric', 'min:0'],
            'allow_pipeline' => ['nullable', 'boolean'],
        ], [
            'ctc.numeric' => 'CTC total must be a number, such as 200000 or 2 Lac.',
            'fixed.numeric' => 'CTC fixed must be a number, such as 200000 or 2 Lac.',
            'variable.numeric' => 'CTC variable must be a number, such as 50000 or 3 Lac.',
        ]);

        return [
            'document_type' => $data['document_type'],
            'legal_entity_id' => (string) $data['legal_entity_id'],
            'candidate_id' => isset($data['candidate_id']) ? (string) $data['candidate_id'] : null,
            'given_name' => $data['given_name'],
            'family_name' => $data['family_name'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'address' => $data['address'] ?? null,
            'department' => $data['department'] ?? null,
            'company_address' => $data['company_address'] ?? null,
            'company_phone' => $data['company_phone'] ?? null,
            'company_website' => $data['company_website'] ?? null,
            'company_email' => $data['company_email'] ?? null,
            'letter_date' => $data['letter_date'] ?? null,
            'signatory_name' => $data['signatory_name'] ?? null,
            'signatory_title' => $data['signatory_title'] ?? null,
            'signatory_contact' => $data['signatory_contact'] ?? null,
            'designation' => $data['designation'],
            'employee_code' => $data['employee_code'] ?? null,
            'joining_date' => $data['joining_date'] ?? null,
            'end_date' => $data['end_date'] ?? null,
            'ctc' => isset($data['ctc']) ? (string) $data['ctc'] : null,
            'fixed' => isset($data['fixed']) ? (string) $data['fixed'] : null,
            'variable' => isset($data['variable']) ? (string) $data['variable'] : null,
            'allow_pipeline' => ! empty($data['allow_pipeline']) ? '1' : null,
        ];
    }

    private function money(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }
        $raw = strtolower(trim($value));
        if ($raw === '') {
            return null;
        }
        $raw = str_replace([',', ' ', '₹'], '', $raw);
        $raw = preg_replace('/^rs\.?/', '', $raw) ?? $raw;
        if (! preg_match('/^(\d+(?:\.\d+)?)(lakh|lac|l|crore|cr)?$/', $raw, $match)) {
            return $value;
        }
        $amount = (float) $match[1];
        $amount *= match ($match[2] ?? '') {
            'lakh', 'lac', 'l' => 100000,
            'crore', 'cr' => 10000000,
            default => 1,
        };

        return rtrim(rtrim(number_format($amount, 2, '.', ''), '0'), '.');
    }

    private function entity(int $id): LegalEntity
    {
        $entity = LegalEntity::query()->find($id);
        if ($entity === null) {
            abort(404, 'The requested resource was not found.');
        }

        return $entity;
    }
}
