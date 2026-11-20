<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class EmployeeReportingRelationship extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['employee_id', 'manager_employee_id', 'effective_on', 'ended_on'];
}
