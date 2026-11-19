<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class LeaveAccrualEntry extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['employee_id', 'leave_type_id', 'year', 'month', 'days'];

    protected function casts(): array
    {
        return ['days' => 'decimal:2'];
    }
}
