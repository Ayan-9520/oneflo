<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobGrade extends Model
{
    protected $fillable = ['organization_id', 'name', 'code', 'status'];
}
