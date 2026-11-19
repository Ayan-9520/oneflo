<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class BusinessUnit extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['legal_entity_id', 'name'];
}
