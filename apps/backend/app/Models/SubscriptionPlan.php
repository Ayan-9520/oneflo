<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlan extends Model
{
    protected $fillable = ['code', 'name', 'interval', 'trial_days', 'price_paise', 'currency', 'employee_limit', 'features', 'status'];

    protected function casts(): array
    {
        return ['features' => 'array'];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'plan_id');
    }
}
