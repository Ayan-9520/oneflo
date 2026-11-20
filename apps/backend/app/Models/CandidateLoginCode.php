<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CandidateLoginCode extends Model
{
    protected $fillable = ['channel', 'destination', 'code_hash', 'attempts', 'expires_at', 'used_at'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'used_at' => 'datetime', 'attempts' => 'integer'];
    }
}
