<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class Asset extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['asset_code', 'category', 'name', 'condition', 'status'];
}
