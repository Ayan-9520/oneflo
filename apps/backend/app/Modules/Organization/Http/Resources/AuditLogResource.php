<?php

declare(strict_types=1);

namespace App\Modules\Organization\Http\Resources;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AuditLog */
class AuditLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'action' => $this->action,
            'actor_user_id' => $this->actor_user_id,
            'organization_id' => $this->organization_id,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at,
        ];
    }
}
