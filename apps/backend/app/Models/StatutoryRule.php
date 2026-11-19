<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class StatutoryRule extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['legal_entity_id', 'code', 'effective_on', 'ended_on', 'config', 'citation', 'confirmed_at'];

    protected function casts(): array
    {
        return [
            'effective_on' => 'date',
            'ended_on' => 'date',
            'config' => 'array',
            'confirmed_at' => 'datetime',
        ];
    }
}
