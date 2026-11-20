<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class ReferralRewardPolicy extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['job_opening_id', 'enabled', 'amount', 'milestone'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean'];
    }
}
