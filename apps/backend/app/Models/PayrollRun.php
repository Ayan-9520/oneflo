<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollRun extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['legal_entity_id', 'year', 'month', 'status', 'warnings', 'open_period', 'submitted_by', 'approved_by', 'locked_by', 'locked_at'];

    protected function casts(): array
    {
        return ['warnings' => 'array', 'locked_at' => 'datetime'];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PayrollLine::class);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, ['draft', 'preview'], true);
    }
}
