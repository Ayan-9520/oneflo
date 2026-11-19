<?php

declare(strict_types=1);

namespace App\Modules\Saas;

use App\Models\Organization;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Modules\Notifications\NotificationService;

final class WorkflowRunner
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function dispatch(Organization $organization, string $event, string $subject): void
    {
        $definitions = WorkflowDefinition::withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('event', $event)
            ->where('enabled', true)
            ->get();
        foreach ($definitions as $definition) {
            $this->run($organization, $definition, $subject);
        }
    }

    public function run(Organization $organization, WorkflowDefinition $definition, string $subject): void
    {
        foreach ($definition->steps ?? [] as $step) {
            $permission = is_array($step) ? (string) ($step['permission'] ?? '') : '';
            if ($permission === '') {
                continue;
            }
            $this->notifications->publish(
                $organization,
                $this->notifications->holders($organization, $permission),
                'workflow.fired',
                'workflow:'.$definition->id.':'.$subject,
                $definition->name,
                'Workflow '.$definition->name.' ran for '.$subject.'.',
            );
        }
    }
}
