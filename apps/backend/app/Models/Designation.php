<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class Designation extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'name', 'code', 'department_id', 'job_level_id', 'job_grade_id', 'employment_type_id',
        'reports_to_designation_id', 'designation_template_id', 'description',
        'min_experience_years', 'max_experience_years', 'responsibilities', 'skills',
        'salary_grade_code', 'status', 'archived_at',
    ];
}
