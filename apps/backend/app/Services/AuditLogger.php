<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditLogger
{
    public function __construct(private readonly Request $request) {}

    public function crossOrganization(User $actor, Organization $organization, string $action, ?Model $auditable = null, array $metadata = []): void
    {
        if (! $actor->hasPlatformRole()) {
            return;
        }

        $this->write($actor, $organization, $action, $auditable, $metadata);
    }

    public function accessChange(User $actor, Organization $organization, string $action, array $metadata = []): void
    {
        $this->write($actor, $organization, $action, null, $metadata);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function platform(User $actor, string $action, array $metadata = []): void
    {
        AuditLog::query()->create([
            'actor_user_id' => $actor->id,
            'organization_id' => null,
            'action' => $action,
            'metadata' => $metadata,
            'ip_address' => $this->request->ip(),
            'created_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function write(User $actor, Organization $organization, string $action, ?Model $auditable, array $metadata): void
    {
        AuditLog::query()->create([
            'actor_user_id' => $actor->id,
            'organization_id' => $organization->id,
            'action' => $action,
            'auditable_type' => $auditable?->getMorphClass(),
            'auditable_id' => $auditable?->getKey(),
            'metadata' => $metadata,
            'ip_address' => $this->request->ip(),
            'created_at' => now(),
        ]);
    }
}
