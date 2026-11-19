<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class AttendanceSetting extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['approval_steps', 'location_tracking_enabled'];

    protected function casts(): array
    {
        return [
            'approval_steps' => 'array',
            'location_tracking_enabled' => 'boolean',
        ];
    }
}
