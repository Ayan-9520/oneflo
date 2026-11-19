<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class EmployeeEducation extends Model
{
    use BelongsToOrganization;

    protected $table = 'employee_educations';

    protected $fillable = ['employee_id', 'qualification', 'institution', 'completed_year'];
}
