<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollLine extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['payroll_run_id', 'employee_id', 'lop_days', 'earnings', 'deductions', 'employer', 'gross_paise', 'deduction_paise', 'net_paise'];

    protected function casts(): array
    {
        return ['earnings' => 'array', 'deductions' => 'array', 'employer' => 'array'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }
}
