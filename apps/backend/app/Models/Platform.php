<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Platform extends Model
{
    protected $fillable = ['name', 'slug'];

    public function organizations(): HasMany
    {
        return $this->hasMany(Organization::class);
    }
}
