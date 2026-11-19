<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class Shift extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['name', 'starts_at', 'ends_at', 'grace_minutes', 'early_grace_minutes', 'overtime_threshold_minutes', 'weekly_offs'];

    protected function casts(): array
    {
        return ['weekly_offs' => 'array'];
    }
}
