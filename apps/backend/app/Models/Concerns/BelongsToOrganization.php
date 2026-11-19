<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Services\OrganizationContext;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

trait BelongsToOrganization
{
    public static function bootBelongsToOrganization(): void
    {
        static::addGlobalScope('organization', function ($query): void {
            $organizationId = app(OrganizationContext::class)->id();

            if ($organizationId !== null) {
                $query->where($query->getModel()->getTable().'.organization_id', $organizationId);
            }
        });

        static::creating(function (Model $model): void {
            $organizationId = app(OrganizationContext::class)->id();

            if ($organizationId === null) {
                return;
            }

            if ($model->getAttribute('organization_id') === null) {
                $model->setAttribute('organization_id', $organizationId);
            }

            if ((int) $model->getAttribute('organization_id') !== $organizationId) {
                throw new AccessDeniedHttpException('The record organization does not match the active organization.');
            }
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
