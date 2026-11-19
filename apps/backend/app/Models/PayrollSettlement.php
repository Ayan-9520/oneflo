<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class PayrollSettlement extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['employee_id', 'last_working_on', 'gratuity_paise', 'leave_encashment_paise', 'net_paise', 'detail'];

    protected function casts(): array
    {
        return ['last_working_on' => 'date', 'detail' => 'array'];
    }
}
