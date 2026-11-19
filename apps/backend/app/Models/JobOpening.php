<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class JobOpening extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['job_requisition_id', 'title', 'description', 'status', 'opened_on'];

    protected function casts(): array
    {
        return ['opened_on' => 'date'];
    }
}
