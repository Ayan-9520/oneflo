<?php

declare(strict_types=1);

namespace App\Modules\Organization\Http\Resources;

use App\Models\OrganizationDomain;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin OrganizationDomain */
class OrganizationDomainResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'host' => $this->host,
            'status' => $this->status,
            'verification_name' => 'oneflo-domain-verification',
            'verification_token' => $this->verification_token,
            'verified_at' => $this->verified_at?->toIso8601String(),
        ];
    }
}
