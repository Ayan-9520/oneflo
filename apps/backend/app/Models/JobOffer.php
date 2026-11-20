<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobOffer extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['job_application_id', 'legal_entity_id', 'designation', 'compensation_note', 'status', 'response_token', 'decision_note', 'submitted_by', 'approved_by', 'sent_at', 'accepted_at', 'declined_at'];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'accepted_at' => 'datetime',
            'declined_at' => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(JobApplication::class, 'job_application_id');
    }
}
