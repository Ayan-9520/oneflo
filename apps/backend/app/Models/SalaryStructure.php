<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalaryStructure extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['legal_entity_id', 'name', 'effective_on'];

    protected function casts(): array
    {
        return ['effective_on' => 'date'];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SalaryStructureLine::class);
    }
}
