<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class EmergencyContact extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['employee_id', 'name', 'relationship', 'phone'];
}
