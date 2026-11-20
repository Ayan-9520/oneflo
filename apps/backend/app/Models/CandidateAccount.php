<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CandidateAccount extends Model
{
    protected $fillable = [
        'full_name',
        'email',
        'phone',
        'email_verified_at',
        'phone_verified_at',
        'google_id',
        'linkedin_id',
        'avatar_url',
        'headline',
        'location',
        'experience_years',
        'current_company',
        'current_designation',
        'current_salary',
        'expected_salary',
        'notice_period',
        'qualification',
        'skills',
        'last_login_at',
        'password',
        'work_status',
        'marketing_opt_in',
        'terms_accepted_at',
    ];

    protected $hidden = ['google_id', 'linkedin_id', 'password'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'experience_years' => 'integer',
            'password' => 'hashed',
            'marketing_opt_in' => 'boolean',
            'terms_accepted_at' => 'datetime',
        ];
    }

    public function tokens(): HasMany
    {
        return $this->hasMany(CandidateToken::class);
    }
}
