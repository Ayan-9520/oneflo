<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class DocumentSequence extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['series', 'last_number'];
}
