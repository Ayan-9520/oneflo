<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProfileConnection extends Model
{
    protected $fillable = ['candidate_account_id', 'from_name', 'from_email', 'role', 'message'];

    public function account(): BelongsTo
    {
        return $this->belongsTo(CandidateAccount::class, 'candidate_account_id');
    }
}
