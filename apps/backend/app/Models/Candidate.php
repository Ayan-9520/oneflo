<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class Candidate extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['candidate_account_id', 'given_name', 'family_name', 'email', 'phone', 'source', 'status', 'location', 'experience_years', 'current_company', 'current_designation', 'notice_period', 'qualification', 'skills', 'is_test_data', 'notes', 'candidate_import_id'];

    protected function casts(): array
    {
        return ['is_test_data' => 'boolean'];
    }

    public function fullName(): string
    {
        return trim($this->given_name.' '.$this->family_name);
    }
}
