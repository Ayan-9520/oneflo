<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class CandidateResume extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['candidate_id', 'original_name', 'path'];
}
