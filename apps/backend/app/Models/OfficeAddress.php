<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfficeAddress extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'company_profile_id',
        'label',
        'line_1',
        'line_2',
        'city',
        'state',
        'postal_code',
        'country',
    ];

    public function companyProfile(): BelongsTo
    {
        return $this->belongsTo(CompanyProfile::class);
    }
}
