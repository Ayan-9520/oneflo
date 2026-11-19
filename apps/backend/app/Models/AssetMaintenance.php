<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class AssetMaintenance extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['asset_id', 'performed_on', 'notes'];

    protected function casts(): array
    {
        return ['performed_on' => 'date'];
    }
}
