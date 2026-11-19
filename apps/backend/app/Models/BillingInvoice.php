<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class BillingInvoice extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['subscription_id', 'amount_paise', 'currency', 'period_start', 'period_end', 'status', 'reference', 'recorded_by'];

    protected function casts(): array
    {
        return ['period_start' => 'date', 'period_end' => 'date'];
    }
}
