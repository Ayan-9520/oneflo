<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class JobOpening extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['job_requisition_id', 'title', 'description', 'status', 'opened_on', 'slug', 'is_public', 'location', 'employment_type', 'category', 'experience_min', 'experience_max', 'salary_public', 'salary_note', 'show_headcount', 'responsibilities', 'qualifications', 'skills', 'benefits', 'deadline_on', 'published_at'];

    protected function casts(): array
    {
        return [
            'opened_on' => 'date',
            'deadline_on' => 'date',
            'published_at' => 'datetime',
            'is_public' => 'boolean',
            'salary_public' => 'boolean',
            'show_headcount' => 'boolean',
        ];
    }

    public function requisition(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(JobRequisition::class, 'job_requisition_id');
    }

    public function organization(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
