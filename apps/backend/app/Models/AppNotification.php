<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AppNotification extends Model
{
    use BelongsToOrganization;

    protected $table = 'notifications';

    protected $fillable = ['user_id', 'event', 'dedupe_key', 'title', 'body', 'in_app', 'read_at'];

    protected function casts(): array
    {
        return ['in_app' => 'boolean', 'read_at' => 'datetime'];
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(NotificationDelivery::class, 'notification_id');
    }
}
