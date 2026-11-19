<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class Candidate extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['given_name', 'family_name', 'email', 'phone', 'source', 'status'];

    public function fullName(): string
    {
        return trim($this->given_name.' '.$this->family_name);
    }
}
