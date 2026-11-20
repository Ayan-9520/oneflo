<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfferApproval extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['job_offer_id', 'approver_user_id', 'decision', 'comments', 'acted_at'];

    protected function casts(): array
    {
        return ['acted_at' => 'datetime'];
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(JobOffer::class, 'job_offer_id');
    }
}
