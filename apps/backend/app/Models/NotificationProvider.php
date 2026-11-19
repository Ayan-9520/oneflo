<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class NotificationProvider extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['channel', 'endpoint', 'sender', 'secret', 'enabled'];

    protected function casts(): array
    {
        return ['secret' => 'encrypted', 'enabled' => 'boolean'];
    }
}
