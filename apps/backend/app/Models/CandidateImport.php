<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class CandidateImport extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'user_id', 'file_name', 'status', 'headers', 'mapping', 'rows',
        'total_rows', 'valid_rows', 'invalid_rows', 'duplicate_rows', 'imported_rows', 'failed_rows', 'imported_at',
    ];

    protected $hidden = ['rows'];

    protected function casts(): array
    {
        return [
            'headers' => 'array',
            'mapping' => 'array',
            'rows' => 'array',
            'imported_at' => 'datetime',
        ];
    }
}
