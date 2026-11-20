<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeReferral extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['employee_id', 'job_opening_id', 'token', 'status', 'note', 'candidate_id', 'job_application_id', 'expires_at'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function opening(): BelongsTo
    {
        return $this->belongsTo(JobOpening::class, 'job_opening_id');
    }
}
