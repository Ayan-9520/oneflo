<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetAllocation extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['asset_id', 'employee_id', 'allocated_on', 'returned_on', 'return_condition'];

    protected function casts(): array
    {
        return ['allocated_on' => 'date', 'returned_on' => 'date'];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
