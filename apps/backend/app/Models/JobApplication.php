<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobApplication extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['job_opening_id', 'candidate_id', 'stage', 'source', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'message', 'current_salary', 'expected_salary', 'consented_at', 'privacy_version', 'acknowledgement_status'];

    protected function casts(): array
    {
        return ['consented_at' => 'datetime'];
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    public function opening(): BelongsTo
    {
        return $this->belongsTo(JobOpening::class, 'job_opening_id');
    }
}
