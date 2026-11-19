<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpenseClaim extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['employee_id', 'expense_category_id', 'amount', 'incurred_on', 'description', 'status', 'receipt_path', 'rejection_reason', 'manager_approved_by', 'finance_approved_by'];

    protected function casts(): array
    {
        return ['incurred_on' => 'date'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }
}
