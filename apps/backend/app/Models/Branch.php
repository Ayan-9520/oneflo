<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['business_unit_id', 'name', 'code'];
}
