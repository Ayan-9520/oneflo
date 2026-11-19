<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Recognition extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['employee_id', 'message', 'awarded_on'];

    protected function casts(): array
    {
        return ['awarded_on' => 'date'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
