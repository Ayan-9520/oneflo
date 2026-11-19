<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class Survey extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['title', 'question', 'closes_on'];

    protected function casts(): array
    {
        return ['closes_on' => 'date'];
    }
}
