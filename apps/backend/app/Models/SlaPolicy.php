<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class SlaPolicy extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['priority', 'response_hours'];
}
