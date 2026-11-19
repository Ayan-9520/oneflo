<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShiftRotationSlot extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['shift_rotation_id', 'position', 'shift_id', 'day_count'];

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }
}
