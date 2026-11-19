<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BrandIdentity extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'brand_id',
        'logo_path',
        'alternate_logo_path',
        'light_logo_path',
        'dark_logo_path',
        'letterhead_path',
        'primary_color',
        'secondary_color',
        'header_text',
        'footer_text',
    ];

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }
}
