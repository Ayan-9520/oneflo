<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class DocumentCategory extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['code', 'name', 'sensitive', 'requires_retention'];

    protected function casts(): array
    {
        return ['sensitive' => 'boolean', 'requires_retention' => 'boolean'];
    }
}
