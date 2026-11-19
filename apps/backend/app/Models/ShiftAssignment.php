<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShiftAssignment extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['employee_id', 'shift_id', 'effective_on', 'ended_on'];

    protected function casts(): array
    {
        return ['effective_on' => 'date', 'ended_on' => 'date'];
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }
}
