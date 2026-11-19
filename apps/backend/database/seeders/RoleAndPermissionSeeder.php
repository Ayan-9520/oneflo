<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Support\Permissions;
use Illuminate\Database\Seeder;

class RoleAndPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [];

        foreach (Permissions::catalog() as $name => $description) {
            $permissions[$name] = Permission::query()->updateOrCreate(
                ['name' => $name],
                ['description' => $description],
            );
        }

        foreach ($this->roles() as $slug => $definition) {
            $role = Role::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $definition['name'],
                    'scope' => $definition['scope'],
                    'description' => $definition['description'],
                ],
            );

            $role->permissions()->sync(array_map(
                fn (string $name) => $permissions[$name]->id,
                $definition['permissions'],
            ));
        }
    }

    /**
     * @return array<string, array{name: string, scope: string, description: string, permissions: list<string>}>
     */
    private function roles(): array
    {
        $all = array_keys(Permissions::catalog());

        return [
            'super-admin' => [
                'name' => 'Super Admin',
                'scope' => 'platform',
                'description' => 'Full access to every organization on the platform.',
                'permissions' => $all,
            ],
            'platform-support-admin' => [
                'name' => 'Platform Support Admin',
                'scope' => 'platform',
                'description' => 'Read-only support access across organizations.',
                'permissions' => [
                    Permissions::ORGANIZATIONS_VIEW,
                    Permissions::ROLES_VIEW,
                    Permissions::EMPLOYEES_VIEW,
                    Permissions::ATTENDANCE_VIEW,
                    Permissions::LEAVE_VIEW,
                    Permissions::DOCUMENTS_VIEW,
                    Permissions::REPORTS_VIEW,
                ],
            ],
            'group-admin' => [
                'name' => 'Group Admin',
                'scope' => 'organization',
                'description' => 'Administers an assigned business group.',
                'permissions' => [
                    Permissions::ORGANIZATIONS_VIEW,
                    Permissions::ORGANIZATION_MANAGE,
                    Permissions::BRANDING_MANAGE,
                    Permissions::ACCESS_MANAGE,
                    Permissions::USERS_MANAGE,
                    Permissions::ROLES_VIEW,
                    Permissions::EMPLOYEES_VIEW,
                    Permissions::EMPLOYEES_CREATE,
                    Permissions::EMPLOYEES_UPDATE,
                    Permissions::EMPLOYEES_DELETE,
                    Permissions::ATTENDANCE_VIEW,
                    Permissions::ATTENDANCE_APPROVE,
                    Permissions::LEAVE_VIEW,
                    Permissions::LEAVE_APPROVE,
                    Permissions::PAYROLL_VIEW,
                    Permissions::DOCUMENTS_VIEW,
                    Permissions::DOCUMENTS_GENERATE,
                    Permissions::REPORTS_VIEW,
                    Permissions::REPORTS_EXPORT,
                ],
            ],
            'group-hr-admin' => [
                'name' => 'Group HR Admin',
                'scope' => 'organization',
                'description' => 'HR administration across assigned organizations.',
                'permissions' => [
                    Permissions::ORGANIZATIONS_VIEW,
                    Permissions::ACCESS_MANAGE,
                    Permissions::USERS_MANAGE,
                    Permissions::ROLES_VIEW,
                    Permissions::EMPLOYEES_VIEW,
                    Permissions::EMPLOYEES_CREATE,
                    Permissions::EMPLOYEES_UPDATE,
                    Permissions::ATTENDANCE_VIEW,
                    Permissions::ATTENDANCE_APPROVE,
                    Permissions::LEAVE_VIEW,
                    Permissions::LEAVE_APPROVE,
                    Permissions::DOCUMENTS_VIEW,
                    Permissions::DOCUMENTS_GENERATE,
                    Permissions::DOCUMENTS_IDENTITY,
                    Permissions::RECRUITMENT_VIEW,
                    Permissions::RECRUITMENT_MANAGE,
                    Permissions::ONBOARDING_MANAGE,
                    Permissions::PERFORMANCE_VIEW,
                    Permissions::PERFORMANCE_MANAGE,
                    Permissions::ASSETS_VIEW,
                    Permissions::ASSETS_MANAGE,
                    Permissions::EXPENSES_VIEW,
                    Permissions::EXPENSES_APPROVE,
                    Permissions::HELPDESK_VIEW,
                    Permissions::HELPDESK_MANAGE,
                    Permissions::ENGAGEMENT_VIEW,
                    Permissions::ENGAGEMENT_MANAGE,
                    Permissions::NOTIFICATIONS_MANAGE,
                    Permissions::REPORTS_VIEW,
                    Permissions::REPORTS_EXPORT,
                ],
            ],
            'group-finance-admin' => [
                'name' => 'Group Finance Admin',
                'scope' => 'organization',
                'description' => 'Finance administration across assigned organizations.',
                'permissions' => [
                    Permissions::ORGANIZATIONS_VIEW,
                    Permissions::EMPLOYEES_VIEW,
                    Permissions::PAYROLL_VIEW,
                    Permissions::PAYROLL_PROCESS,
                    Permissions::EXPENSES_VIEW,
                    Permissions::EXPENSES_APPROVE,
                    Permissions::REPORTS_VIEW,
                    Permissions::REPORTS_EXPORT,
                ],
            ],
            'business-admin' => [
                'name' => 'Business Admin',
                'scope' => 'organization',
                'description' => 'Administers one assigned business.',
                'permissions' => [
                    Permissions::ORGANIZATIONS_VIEW,
                    Permissions::ORGANIZATION_MANAGE,
                    Permissions::BRANDING_MANAGE,
                    Permissions::ACCESS_MANAGE,
                    Permissions::USERS_MANAGE,
                    Permissions::ROLES_VIEW,
                    Permissions::EMPLOYEES_VIEW,
                    Permissions::EMPLOYEES_CREATE,
                    Permissions::EMPLOYEES_UPDATE,
                    Permissions::EMPLOYEES_DELETE,
                    Permissions::ATTENDANCE_VIEW,
                    Permissions::ATTENDANCE_APPROVE,
                    Permissions::LEAVE_VIEW,
                    Permissions::LEAVE_APPROVE,
                    Permissions::PAYROLL_VIEW,
                    Permissions::DOCUMENTS_VIEW,
                    Permissions::DOCUMENTS_GENERATE,
                    Permissions::DOCUMENTS_IDENTITY,
                    Permissions::RECRUITMENT_VIEW,
                    Permissions::RECRUITMENT_MANAGE,
                    Permissions::ONBOARDING_MANAGE,
                    Permissions::PERFORMANCE_VIEW,
                    Permissions::PERFORMANCE_MANAGE,
                    Permissions::ASSETS_VIEW,
                    Permissions::ASSETS_MANAGE,
                    Permissions::EXPENSES_VIEW,
                    Permissions::EXPENSES_APPROVE,
                    Permissions::HELPDESK_VIEW,
                    Permissions::HELPDESK_MANAGE,
                    Permissions::ENGAGEMENT_VIEW,
                    Permissions::ENGAGEMENT_MANAGE,
                    Permissions::NOTIFICATIONS_MANAGE,
                    Permissions::REPORTS_VIEW,
                    Permissions::REPORTS_EXPORT,
                    Permissions::BILLING_VIEW,
                    Permissions::AI_USE,
                    Permissions::INTEGRATIONS_MANAGE,
                    Permissions::WORKFLOWS_MANAGE,
                ],
            ],
            'hr-admin' => [
                'name' => 'HR Admin',
                'scope' => 'organization',
                'description' => 'HR administration for one assigned business.',
                'permissions' => [
                    Permissions::EMPLOYEES_VIEW,
                    Permissions::EMPLOYEES_CREATE,
                    Permissions::EMPLOYEES_UPDATE,
                    Permissions::ACCESS_MANAGE,
                    Permissions::USERS_MANAGE,
                    Permissions::ROLES_VIEW,
                    Permissions::ATTENDANCE_VIEW,
                    Permissions::ATTENDANCE_APPROVE,
                    Permissions::LEAVE_VIEW,
                    Permissions::LEAVE_APPROVE,
                    Permissions::DOCUMENTS_VIEW,
                    Permissions::DOCUMENTS_GENERATE,
                    Permissions::DOCUMENTS_IDENTITY,
                    Permissions::RECRUITMENT_VIEW,
                    Permissions::RECRUITMENT_MANAGE,
                    Permissions::ONBOARDING_MANAGE,
                    Permissions::PERFORMANCE_VIEW,
                    Permissions::PERFORMANCE_MANAGE,
                    Permissions::ASSETS_VIEW,
                    Permissions::ASSETS_MANAGE,
                    Permissions::EXPENSES_VIEW,
                    Permissions::EXPENSES_APPROVE,
                    Permissions::HELPDESK_VIEW,
                    Permissions::HELPDESK_MANAGE,
                    Permissions::ENGAGEMENT_VIEW,
                    Permissions::ENGAGEMENT_MANAGE,
                    Permissions::NOTIFICATIONS_MANAGE,
                    Permissions::REPORTS_VIEW,
                    Permissions::AI_USE,
                    Permissions::WORKFLOWS_MANAGE,
                ],
            ],
            'hr-executive' => [
                'name' => 'HR Executive',
                'scope' => 'organization',
                'description' => 'Maintains employee records for one assigned business.',
                'permissions' => [
                    Permissions::EMPLOYEES_VIEW,
                    Permissions::EMPLOYEES_CREATE,
                    Permissions::EMPLOYEES_UPDATE,
                    Permissions::ATTENDANCE_VIEW,
                    Permissions::LEAVE_VIEW,
                    Permissions::DOCUMENTS_VIEW,
                ],
            ],
            'payroll-admin' => [
                'name' => 'Payroll Admin',
                'scope' => 'organization',
                'description' => 'Processes payroll for one assigned business.',
                'permissions' => [
                    Permissions::EMPLOYEES_VIEW,
                    Permissions::PAYROLL_VIEW,
                    Permissions::PAYROLL_PROCESS,
                    Permissions::REPORTS_VIEW,
                    Permissions::REPORTS_EXPORT,
                ],
            ],
            'recruiter' => [
                'name' => 'Recruiter',
                'scope' => 'organization',
                'description' => 'Creates candidate and employee records for one assigned business.',
                'permissions' => [
                    Permissions::EMPLOYEES_VIEW,
                    Permissions::EMPLOYEES_CREATE,
                    Permissions::DOCUMENTS_VIEW,
                    Permissions::RECRUITMENT_VIEW,
                    Permissions::RECRUITMENT_MANAGE,
                ],
            ],
            'department-manager' => [
                'name' => 'Department Manager',
                'scope' => 'organization',
                'description' => 'Manages an authorized department.',
                'permissions' => [
                    Permissions::EMPLOYEES_VIEW_TEAM,
                    Permissions::ATTENDANCE_VIEW,
                    Permissions::ATTENDANCE_APPROVE,
                    Permissions::LEAVE_VIEW,
                    Permissions::LEAVE_APPROVE,
                    Permissions::PERFORMANCE_VIEW,
                    Permissions::PERFORMANCE_MANAGE,
                    Permissions::EXPENSES_VIEW,
                    Permissions::EXPENSES_APPROVE,
                    Permissions::HELPDESK_VIEW,
                    Permissions::REPORTS_VIEW,
                ],
            ],
            'team-leader' => [
                'name' => 'Team Leader',
                'scope' => 'organization',
                'description' => 'Views an authorized team.',
                'permissions' => [
                    Permissions::EMPLOYEES_VIEW_TEAM,
                    Permissions::ATTENDANCE_VIEW,
                    Permissions::LEAVE_VIEW,
                ],
            ],
            'accountant' => [
                'name' => 'Accountant',
                'scope' => 'organization',
                'description' => 'Views payroll for one assigned business.',
                'permissions' => [
                    Permissions::PAYROLL_VIEW,
                    Permissions::EXPENSES_VIEW,
                    Permissions::EXPENSES_APPROVE,
                    Permissions::REPORTS_VIEW,
                    Permissions::REPORTS_EXPORT,
                ],
            ],
            'employee' => [
                'name' => 'Employee',
                'scope' => 'organization',
                'description' => 'Access limited to the signed-in employee record.',
                'permissions' => [
                    Permissions::EMPLOYEES_VIEW_OWN,
                    Permissions::ATTENDANCE_VIEW,
                    Permissions::LEAVE_VIEW,
                    Permissions::DOCUMENTS_VIEW,
                    Permissions::PERFORMANCE_VIEW,
                    Permissions::EXPENSES_VIEW,
                    Permissions::EXPENSES_SUBMIT,
                    Permissions::HELPDESK_VIEW,
                    Permissions::ENGAGEMENT_VIEW,
                    Permissions::PAYROLL_VIEW_OWN,
                ],
            ],
        ];
    }
}
