<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceDay extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'employee_id', 'shift_id', 'work_date', 'work_mode', 'status',
        'worked_minutes', 'break_minutes', 'scheduled_minutes', 'late_minutes', 'early_minutes', 'overtime_minutes', 'note',
    ];

    protected function casts(): array
    {
        return ['work_date' => 'date'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
