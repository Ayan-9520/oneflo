<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeFile extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['employee_id', 'category', 'label', 'path', 'sensitive', 'encrypted', 'reference_last4', 'retention_until', 'uploaded_by'];

    protected function casts(): array
    {
        return [
            'sensitive' => 'boolean',
            'encrypted' => 'boolean',
            'retention_until' => 'date',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
