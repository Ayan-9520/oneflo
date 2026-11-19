<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PerformanceReview extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['employee_id', 'cycle', 'period_on', 'self_comments', 'self_score', 'manager_comments', 'manager_score', 'promotion_recommended', 'status', 'reviewed_by'];

    protected function casts(): array
    {
        return ['period_on' => 'date', 'promotion_recommended' => 'boolean'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
