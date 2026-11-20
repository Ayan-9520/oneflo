<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class DesignationDepartmentMapping extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['designation_id', 'department_id'];
}
