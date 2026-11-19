<?php

declare(strict_types=1);

namespace App\Services;

interface DomainOwnershipCheck
{
    public function owns(string $host, string $token): bool;
}
