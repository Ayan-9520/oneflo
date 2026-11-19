<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class SavedReportFilter extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['user_id', 'name', 'report', 'filters'];

    protected function casts(): array
    {
        return ['filters' => 'array'];
    }
}
