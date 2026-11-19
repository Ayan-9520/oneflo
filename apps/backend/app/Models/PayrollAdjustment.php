<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class PayrollAdjustment extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['payroll_run_id', 'employee_id', 'component_code', 'amount_paise', 'reason', 'created_by'];
}
