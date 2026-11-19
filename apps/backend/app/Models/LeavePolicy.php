<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeavePolicy extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['leave_type_id', 'year', 'annual_days', 'accrual_days', 'carry_forward_max', 'encashment_allowed', 'encashment_max', 'approval_steps'];

    protected function casts(): array
    {
        return [
            'annual_days' => 'decimal:2',
            'accrual_days' => 'decimal:2',
            'carry_forward_max' => 'decimal:2',
            'encashment_max' => 'decimal:2',
            'encashment_allowed' => 'boolean',
            'approval_steps' => 'array',
        ];
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }
}
