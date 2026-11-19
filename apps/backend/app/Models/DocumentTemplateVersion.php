<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentTemplateVersion extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'document_template_id',
        'version',
        'body',
        'header_alignment',
        'show_logo',
        'show_stamp',
        'show_signatory',
        'footer_note',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'show_logo' => 'boolean',
            'show_stamp' => 'boolean',
            'show_signatory' => 'boolean',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(DocumentTemplate::class, 'document_template_id');
    }
}
