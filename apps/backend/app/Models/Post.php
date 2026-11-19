<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['kind', 'title', 'body', 'occurs_on'];

    protected function casts(): array
    {
        return ['occurs_on' => 'date'];
    }
}
