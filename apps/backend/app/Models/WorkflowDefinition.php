<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class WorkflowDefinition extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['name', 'event', 'steps', 'enabled'];

    protected function casts(): array
    {
        return ['steps' => 'array', 'enabled' => 'boolean'];
    }
}
