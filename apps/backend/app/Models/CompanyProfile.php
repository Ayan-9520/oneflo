<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CompanyProfile extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'legal_entity_id',
        'legal_company_name',
        'brand_name',
        'registered_address',
        'cin',
        'pan',
        'tan',
        'gstin',
        'contact_email',
        'contact_phone',
        'website',
        'stamp_path',
        'payroll_pf_number',
        'payroll_esi_number',
        'payroll_pt_number',
        'payroll_lin',
    ];

    public function legalEntity(): BelongsTo
    {
        return $this->belongsTo(LegalEntity::class);
    }

    public function offices(): HasMany
    {
        return $this->hasMany(OfficeAddress::class);
    }

    public function signatories(): HasMany
    {
        return $this->hasMany(AuthorizedSignatory::class);
    }
}
