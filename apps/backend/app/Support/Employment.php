<?php

declare(strict_types=1);

namespace App\Support;

final class Employment
{
    public const TYPES = ['permanent', 'contract', 'intern', 'consultant'];

    public const STATUSES = ['active', 'inactive', 'resigned', 'exited', 'transferred'];

    public const CHANGES = [
        'hire',
        'update',
        'promotion',
        'department_change',
        'designation_change',
        'manager_change',
        'transfer',
        'deactivation',
        'rejoining',
        'resignation',
        'exit',
        'bulk_update',
    ];
}
