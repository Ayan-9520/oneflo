<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class PolicyAcceptance extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['employee_id', 'policy_name', 'accepted_on'];

    protected function casts(): array
    {
        return ['accepted_on' => 'date'];
    }
}
