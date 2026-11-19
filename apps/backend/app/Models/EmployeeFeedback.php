<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeFeedback extends Model
{
    use BelongsToOrganization;

    protected $table = 'employee_feedback';

    protected $fillable = ['employee_id', 'body', 'anonymous'];

    protected function casts(): array
    {
        return ['anonymous' => 'boolean'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
