<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\BrandFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Brand extends Model
{
    /** @use HasFactory<BrandFactory> */
    use BelongsToOrganization, HasFactory;

    protected $fillable = ['name', 'slug', 'is_primary', 'logo_path'];

    protected function casts(): array
    {
        return ['is_primary' => 'boolean'];
    }
}
