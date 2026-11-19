<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class ReportSchedule extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['user_id', 'report', 'filters', 'format', 'cadence', 'next_run_on', 'last_error'];

    protected function casts(): array
    {
        return ['filters' => 'array', 'next_run_on' => 'date'];
    }
}
