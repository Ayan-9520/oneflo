<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class ReferralReward extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['employee_referral_id', 'amount', 'status', 'approved_by', 'approved_at', 'payout_status', 'payout_reference', 'notes'];

    protected function casts(): array
    {
        return ['approved_at' => 'datetime'];
    }
}
