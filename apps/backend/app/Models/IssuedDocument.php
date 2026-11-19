<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IssuedDocument extends Model
{
    use BelongsToOrganization;

    public ?string $plainVerificationToken = null;

    protected $fillable = ['legal_entity_id', 'employee_id', 'candidate_id', 'document_type', 'number', 'version', 'issued_on', 'html', 'lines', 'oneflo_attribution', 'created_by', 'voided_at', 'voided_by'];

    protected function casts(): array
    {
        return [
            'issued_on' => 'date',
            'lines' => 'array',
            'oneflo_attribution' => 'boolean',
            'voided_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    public function legalEntity(): BelongsTo
    {
        return $this->belongsTo(LegalEntity::class);
    }
}
