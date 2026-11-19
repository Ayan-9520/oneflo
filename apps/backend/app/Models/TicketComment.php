<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class TicketComment extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['ticket_id', 'user_id', 'body'];
}
