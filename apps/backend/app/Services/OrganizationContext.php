<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Organization;

class OrganizationContext
{
    private ?Organization $organization = null;

    private ?Organization $locked = null;

    public function set(Organization $organization): void
    {
        $this->organization = $organization;
    }

    public function lock(Organization $organization): void
    {
        $this->set($organization);
        $this->locked = $organization;
    }

    public function locked(): ?Organization
    {
        return $this->locked;
    }

    public function forget(): void
    {
        $this->organization = null;
        $this->locked = null;
    }

    public function get(): ?Organization
    {
        return $this->organization;
    }

    public function id(): ?int
    {
        return $this->organization?->id;
    }
}
