<?php

declare(strict_types=1);

namespace App\Modules\Organization\Http\Resources;

use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Organization */
class OrganizationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'status' => $this->status,
            'legal_entities' => $this->whenLoaded('legalEntities', fn () => $this->legalEntities->map(fn ($entity) => [
                'id' => $entity->id,
                'name' => $entity->legal_name,
            ])->values()),
            'brands' => $this->whenLoaded('brands', fn () => $this->brands->map->only(['id', 'name'])->values()),
            'departments' => $this->whenLoaded('departments', fn () => $this->departments->map->only(['id', 'name'])->values()),
            'designations' => $this->whenLoaded('designations', fn () => $this->designations->map->only(['id', 'name'])->values()),
            'branches' => $this->whenLoaded('branches', fn () => $this->branches->map->only(['id', 'name'])->values()),
        ];
    }
}
