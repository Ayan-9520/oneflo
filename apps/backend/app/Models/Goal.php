<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class Goal extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['scope', 'employee_id', 'department_id', 'kpi_id', 'title', 'period', 'period_on', 'target', 'status'];

    protected function casts(): array
    {
        return ['period_on' => 'date'];
    }
}
