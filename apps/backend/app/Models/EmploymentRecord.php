<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmploymentRecord extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'employee_id',
        'person_key',
        'legal_entity_id',
        'brand_id',
        'branch_id',
        'department_id',
        'designation_id',
        'manager_employee_id',
        'employment_type',
        'work_location',
        'status',
        'change_type',
        'effective_on',
        'ended_on',
        'last_working_on',
        'reason',
        'note',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'effective_on' => 'date',
            'ended_on' => 'date',
            'last_working_on' => 'date',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function legalEntity(): BelongsTo
    {
        return $this->belongsTo(LegalEntity::class);
    }
}
