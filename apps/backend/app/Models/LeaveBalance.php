<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class LeaveBalance extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['employee_id', 'leave_type_id', 'year', 'opening_days', 'accrued_days', 'used_days', 'carried_days', 'encashed_days'];

    protected function casts(): array
    {
        return [
            'opening_days' => 'decimal:2',
            'accrued_days' => 'decimal:2',
            'used_days' => 'decimal:2',
            'carried_days' => 'decimal:2',
            'encashed_days' => 'decimal:2',
        ];
    }

    public function availableDays(): string
    {
        return number_format(
            (float) $this->opening_days + (float) $this->accrued_days + (float) $this->carried_days - (float) $this->used_days - (float) $this->encashed_days,
            2,
            '.',
            '',
        );
    }
}
