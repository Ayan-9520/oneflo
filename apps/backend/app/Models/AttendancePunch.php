<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class AttendancePunch extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['employee_id', 'work_date', 'punched_at', 'kind', 'work_mode'];

    protected function casts(): array
    {
        return ['work_date' => 'date', 'punched_at' => 'datetime'];
    }
}
