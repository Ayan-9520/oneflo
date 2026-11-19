<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class PayrollComponent extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['code', 'name', 'kind', 'taxable'];

    protected function casts(): array
    {
        return ['taxable' => 'boolean'];
    }
}
