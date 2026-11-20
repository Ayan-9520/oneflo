<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CandidateToken extends Model
{
    protected $fillable = ['candidate_account_id', 'token_hash', 'expires_at', 'last_used_at'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'last_used_at' => 'datetime'];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(CandidateAccount::class, 'candidate_account_id');
    }
}
