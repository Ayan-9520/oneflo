<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformBranding extends Model
{
    protected $table = 'platform_branding';

    protected $fillable = [
        'logo_path',
        'powered_by_text',
        'website_url',
        'show_footer',
    ];

    protected function casts(): array
    {
        return ['show_footer' => 'boolean'];
    }

    public static function current(): self
    {
        return self::query()->firstOrCreate([], [
            'powered_by_text' => 'Powered by OneFlo',
            'website_url' => config('oneflo.website_url'),
            'show_footer' => true,
        ]);
    }
}
