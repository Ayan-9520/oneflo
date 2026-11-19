<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class TicketAttachment extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['ticket_id', 'original_name', 'path'];
}
