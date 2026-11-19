<?php

declare(strict_types=1);

namespace App\Modules\Organization\Http\Resources;

use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Employee */
class EmployeeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'person_key' => $this->person_key,
            'employee_code' => $this->employee_code,
            'given_name' => $this->given_name,
            'family_name' => $this->family_name,
            'full_name' => $this->fullName(),
            'email' => $this->email,
            'phone' => $this->phone,
            'has_photo' => $this->photo_path !== null,
            'employment_type' => $this->employment_type,
            'joined_on' => $this->joined_on?->toDateString(),
            'work_location' => $this->work_location,
            'status' => $this->status,
            'brand' => $this->brand === null ? null : ['id' => $this->brand->id, 'name' => $this->brand->name],
            'legal_entity' => $this->legalEntity === null ? null : ['id' => $this->legalEntity->id, 'name' => $this->legalEntity->legal_name],
            'department' => $this->department === null ? null : ['id' => $this->department->id, 'name' => $this->department->name],
            'designation' => $this->designation === null ? null : ['id' => $this->designation->id, 'name' => $this->designation->name],
            'branch' => $this->branch === null ? null : ['id' => $this->branch->id, 'name' => $this->branch->name],
            'manager' => $this->manager === null ? null : ['id' => $this->manager->id, 'full_name' => $this->manager->fullName()],
        ];
    }
}
