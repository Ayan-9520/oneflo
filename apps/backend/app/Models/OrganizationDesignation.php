<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class OrganizationDesignation extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['designation_id', 'designation_template_id', 'status', 'effective_on'];
}
