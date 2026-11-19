<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShiftRotationAssignment extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['employee_id', 'shift_rotation_id', 'starts_on', 'ended_on'];

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ended_on' => 'date'];
    }

    public function rotation(): BelongsTo
    {
        return $this->belongsTo(ShiftRotation::class, 'shift_rotation_id');
    }
}
