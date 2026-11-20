<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DesignationTemplate extends Model
{
    protected $fillable = ['category', 'name'];
}
