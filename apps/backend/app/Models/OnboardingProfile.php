<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class OnboardingProfile extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['employee_id', 'probation_ends_on', 'probation_status', 'identity_status'];

    protected function casts(): array
    {
        return ['probation_ends_on' => 'date'];
    }
}
