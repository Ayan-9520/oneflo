<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class NotificationPreference extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['user_id', 'event', 'in_app', 'email', 'sms', 'whatsapp'];

    protected function casts(): array
    {
        return [
            'in_app' => 'boolean',
            'email' => 'boolean',
            'sms' => 'boolean',
            'whatsapp' => 'boolean',
        ];
    }
}
