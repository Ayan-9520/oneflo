<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['legal_entity_id', 'name', 'observed_on'];

    protected function casts(): array
    {
        return ['observed_on' => 'date'];
    }
}
