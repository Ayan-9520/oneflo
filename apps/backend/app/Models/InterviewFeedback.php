<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class InterviewFeedback extends Model
{
    use BelongsToOrganization;

    protected $table = 'interview_feedback';

    protected $fillable = ['interview_id', 'rating', 'comments', 'recommendation'];
}
