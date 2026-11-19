<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Employee extends Model
{
    /** @use HasFactory<EmployeeFactory> */
    use BelongsToOrganization, HasFactory;

    protected $fillable = [
        'user_id',
        'person_key',
        'branch_id',
        'department_id',
        'designation_id',
        'brand_id',
        'legal_entity_id',
        'manager_id',
        'employee_code',
        'given_name',
        'family_name',
        'email',
        'phone',
        'photo_path',
        'employment_type',
        'joined_on',
        'date_of_birth',
        'work_location',
        'status',
    ];

    protected function casts(): array
    {
        return ['joined_on' => 'date', 'date_of_birth' => 'date'];
    }

    protected static function booted(): void
    {
        static::creating(function (Employee $employee): void {
            if ($employee->person_key === null || $employee->person_key === '') {
                $employee->person_key = (string) Str::uuid();
            }
        });
    }

    public function fullName(): string
    {
        return trim($this->given_name.' '.$this->family_name);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function designation(): BelongsTo
    {
        return $this->belongsTo(Designation::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function legalEntity(): BelongsTo
    {
        return $this->belongsTo(LegalEntity::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(self::class, 'manager_id');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(self::class, 'manager_id');
    }

    public function employmentRecords(): HasMany
    {
        return $this->hasMany(EmploymentRecord::class);
    }
}
