<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class OrganizationDepartment extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['department_id', 'department_template_id', 'status', 'effective_on'];
}
