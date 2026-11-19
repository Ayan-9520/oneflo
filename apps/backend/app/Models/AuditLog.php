<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    public $timestamps = false;

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new \LogicException('Audit logs cannot be changed.');
        });
        static::deleting(function (): void {
            throw new \LogicException('Audit logs cannot be deleted.');
        });
    }

    protected $fillable = [
        'actor_user_id',
        'organization_id',
        'action',
        'auditable_type',
        'auditable_id',
        'metadata',
        'ip_address',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
