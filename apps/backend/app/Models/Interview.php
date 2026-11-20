<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class Interview extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['job_application_id', 'scheduled_at', 'interviewer', 'mode', 'round', 'status'];

    protected function casts(): array
    {
        return ['scheduled_at' => 'datetime'];
    }
}
