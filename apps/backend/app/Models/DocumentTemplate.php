<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentTemplate extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'legal_entity_id',
        'document_type',
        'name',
        'scope_key',
    ];

    public function versions(): HasMany
    {
        return $this->hasMany(DocumentTemplateVersion::class);
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(DocumentTemplateVersion::class, 'current_version_id');
    }

    public static function scopeKey(int $organizationId, ?int $legalEntityId, string $documentType): string
    {
        return $organizationId.':'.($legalEntityId ?? 'default').':'.$documentType;
    }
}
