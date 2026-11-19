<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceRequest extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['employee_id', 'work_date', 'type', 'requested_in', 'requested_out', 'reason', 'status', 'current_step', 'approval_steps'];

    protected function casts(): array
    {
        return ['work_date' => 'date', 'approval_steps' => 'array'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
