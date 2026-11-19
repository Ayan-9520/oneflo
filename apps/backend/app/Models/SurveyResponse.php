<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class SurveyResponse extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['survey_id', 'employee_id', 'answer'];
}
