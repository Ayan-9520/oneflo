<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class DocumentSignature extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['issued_document_id', 'signatory_id', 'content_sha256', 'signed_at', 'signed_by'];

    protected function casts(): array
    {
        return ['signed_at' => 'datetime'];
    }
}
