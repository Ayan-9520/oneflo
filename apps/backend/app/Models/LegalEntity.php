<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class LegalEntity extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['brand_id', 'legal_name', 'registration_number', 'employee_code_prefix', 'employee_code_padding'];
}
