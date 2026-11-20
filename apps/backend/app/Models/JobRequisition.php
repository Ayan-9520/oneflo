<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class JobRequisition extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['legal_entity_id', 'title', 'description', 'headcount', 'status', 'department_id', 'designation_id', 'submitted_by', 'submitted_at'];

    protected function casts(): array
    {
        return ['submitted_at' => 'datetime'];
    }
}
