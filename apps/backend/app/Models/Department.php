<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'branch_id', 'name', 'parent_id', 'department_category_id', 'department_template_id',
        'code', 'head_employee_id', 'status', 'description', 'archived_at',
    ];

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }
}
