<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class LeaveType extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['name', 'code', 'paid'];

    protected function casts(): array
    {
        return ['paid' => 'boolean'];
    }
}
