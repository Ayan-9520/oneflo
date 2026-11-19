<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeCompensation extends Model
{
    use BelongsToOrganization;

    protected $table = 'employee_compensations';

    protected $fillable = ['employee_id', 'salary_structure_id', 'tax_regime', 'pan'];

    protected function casts(): array
    {
        return ['pan' => 'encrypted'];
    }

    public function structure(): BelongsTo
    {
        return $this->belongsTo(SalaryStructure::class, 'salary_structure_id');
    }

    public function maskedPan(): ?string
    {
        $pan = $this->pan;
        if ($pan === null || $pan === '') {
            return null;
        }

        return str_repeat('X', max(0, strlen($pan) - 4)).substr($pan, -4);
    }
}
