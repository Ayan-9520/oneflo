<?php

declare(strict_types=1);

namespace App\Modules\Documents\Services;

use App\Models\AuthorizedSignatory;
use App\Models\CompanyProfile;
use App\Models\DocumentSignature;
use App\Models\IssuedDocument;
use App\Models\User;
use App\Support\DocumentTypes;
use ZipArchive;

final class DocumentEngine
{
    public function token(IssuedDocument $document): string
    {
        $token = bin2hex(random_bytes(24));
        $document->verification_hash = hash('sha256', $token);
        $document->save();
        $document->plainVerificationToken = $token;

        return $token;
    }

    /**
     * @param  list<string>  $lines
     */
    public function docx(array $lines): string
    {
        $paragraphs = '';
        foreach ($lines as $line) {
            $paragraphs .= '<w:p><w:r><w:t>'.htmlspecialchars($line, ENT_XML1).'</w:t></w:r></w:p>';
        }
        $document = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>'.$paragraphs.'</w:body></w:document>';
        $files = [
            '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>',
            '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>',
            'word/document.xml' => $document,
        ];
        $path = tempnam(sys_get_temp_dir(), 'docx');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);
        foreach ($files as $name => $contents) {
            $zip->addFromString($name, $contents);
        }
        $zip->close();
        $binary = (string) file_get_contents($path);
        unlink($path);

        return $binary;
    }

    public function sign(IssuedDocument $document, AuthorizedSignatory $signatory, User $actor): DocumentSignature
    {
        $profile = CompanyProfile::query()->find($signatory->company_profile_id);
        if ($profile === null || (int) $profile->legal_entity_id !== (int) $document->legal_entity_id) {
            abort(404, 'The requested resource was not found.');
        }
        $hash = hash('sha256', implode("\n", $document->lines ?? []));

        return DocumentSignature::query()->create([
            'issued_document_id' => $document->id,
            'signatory_id' => $signatory->id,
            'content_sha256' => $hash,
            'signed_at' => now(),
            'signed_by' => $actor->id,
        ]);
    }

    /**
     * @param  list<string>  $lines
     * @return list<string>
     */
    public function signedLines(IssuedDocument $document, array $lines): array
    {
        if (DocumentTypes::isOfficial($document->document_type)) {
            $lines = array_values(array_filter($lines, fn (string $line) => ! str_contains($line, 'Powered by OneFlo')));
        }
        $signature = DocumentSignature::query()->where('issued_document_id', $document->id)->latest('id')->first();
        if ($signature !== null) {
            $signatory = AuthorizedSignatory::query()->find($signature->signatory_id);
            $lines[] = 'Digitally signed by '.($signatory->name ?? 'authorized signatory').' ('.($signatory->designation ?? '').') SHA-256 '.$signature->content_sha256;
        }

        return $lines;
    }
}
