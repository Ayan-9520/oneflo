<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShiftRotation extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['name'];

    public function slots(): HasMany
    {
        return $this->hasMany(ShiftRotationSlot::class)->orderBy('position');
    }
}
