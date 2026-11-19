<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrganizationAccessScope extends Model
{
    protected $fillable = ['organization_access_id', 'scope_type', 'scope_id'];
}
