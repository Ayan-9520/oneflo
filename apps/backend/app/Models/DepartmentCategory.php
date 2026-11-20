<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DepartmentCategory extends Model
{
    protected $fillable = ['name', 'slug'];

    public function templates(): HasMany
    {
        return $this->hasMany(DepartmentTemplate::class);
    }
}
