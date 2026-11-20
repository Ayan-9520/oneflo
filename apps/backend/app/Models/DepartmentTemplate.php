<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DepartmentTemplate extends Model
{
    protected $fillable = ['department_category_id', 'name'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(DepartmentCategory::class, 'department_category_id');
    }
}
