<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProfileFollow extends Model
{
    protected $fillable = ['follower_account_id', 'following_account_id'];

    public function follower(): BelongsTo
    {
        return $this->belongsTo(CandidateAccount::class, 'follower_account_id');
    }

    public function following(): BelongsTo
    {
        return $this->belongsTo(CandidateAccount::class, 'following_account_id');
    }
}
