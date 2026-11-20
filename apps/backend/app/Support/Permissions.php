<?php

declare(strict_types=1);

namespace App\Support;

final class Permissions
{
    public const ORGANIZATIONS_VIEW = 'organizations.view';

    public const ORGANIZATION_MANAGE = 'organization.manage';

    public const BRANDING_MANAGE = 'branding.manage';

    public const ACCESS_MANAGE = 'access.manage';

    public const USERS_MANAGE = 'users.manage';

    public const ROLES_VIEW = 'roles.view';

    public const EMPLOYEES_VIEW = 'employees.view';

    public const EMPLOYEES_VIEW_TEAM = 'employees.view_team';

    public const EMPLOYEES_VIEW_OWN = 'employees.view_own';

    public const EMPLOYEES_CREATE = 'employees.create';

    public const EMPLOYEES_UPDATE = 'employees.update';

    public const EMPLOYEES_DELETE = 'employees.delete';

    public const ATTENDANCE_VIEW = 'attendance.view';

    public const ATTENDANCE_APPROVE = 'attendance.approve';

    public const LEAVE_VIEW = 'leave.view';

    public const LEAVE_APPROVE = 'leave.approve';

    public const PAYROLL_VIEW = 'payroll.view';

    public const PAYROLL_VIEW_OWN = 'payroll.view_own';

    public const PAYROLL_PROCESS = 'payroll.process';

    public const DOCUMENTS_VIEW = 'documents.view';

    public const DOCUMENTS_GENERATE = 'documents.generate';

    public const DOCUMENTS_IDENTITY = 'documents.identity';

    public const RECRUITMENT_VIEW = 'recruitment.view';

    public const RECRUITMENT_MANAGE = 'recruitment.manage';

    public const RECRUITMENT_REQUISITIONS_APPROVE = 'recruitment.requisitions.approve';

    public const RECRUITMENT_OFFERS_APPROVE = 'recruitment.offers.approve';

    public const RECRUITMENT_JOINING_CONFIRM = 'recruitment.joining.confirm';

    public const RECRUITMENT_REWARDS = 'recruitment.rewards.manage';

    public const ONBOARDING_MANAGE = 'onboarding.manage';

    public const PERFORMANCE_VIEW = 'performance.view';

    public const PERFORMANCE_MANAGE = 'performance.manage';

    public const ASSETS_VIEW = 'assets.view';

    public const ASSETS_MANAGE = 'assets.manage';

    public const EXPENSES_VIEW = 'expenses.view';

    public const EXPENSES_SUBMIT = 'expenses.submit';

    public const EXPENSES_APPROVE = 'expenses.approve';

    public const HELPDESK_VIEW = 'helpdesk.view';

    public const HELPDESK_MANAGE = 'helpdesk.manage';

    public const ENGAGEMENT_VIEW = 'engagement.view';

    public const ENGAGEMENT_MANAGE = 'engagement.manage';

    public const NOTIFICATIONS_MANAGE = 'notifications.manage';

    public const REPORTS_VIEW = 'reports.view';

    public const REPORTS_EXPORT = 'reports.export';

    public const BILLING_VIEW = 'billing.view';

    public const BILLING_MANAGE = 'billing.manage';

    public const AI_USE = 'ai.use';

    public const INTEGRATIONS_MANAGE = 'integrations.manage';

    public const WORKFLOWS_MANAGE = 'workflows.manage';

    /**
     * @return array<string, string>
     */
    public static function catalog(): array
    {
        return [
            self::ORGANIZATIONS_VIEW => 'View organizations assigned to the account',
            self::ORGANIZATION_MANAGE => 'Manage organization records',
            self::BRANDING_MANAGE => 'Manage brands and visual identity',
            self::ACCESS_MANAGE => 'Assign roles and business access',
            self::USERS_MANAGE => 'Activate and deactivate user accounts',
            self::ROLES_VIEW => 'View roles and permissions',
            self::EMPLOYEES_VIEW => 'View employees in the assigned organization',
            self::EMPLOYEES_VIEW_TEAM => 'View employees in an authorized team',
            self::EMPLOYEES_VIEW_OWN => 'View the signed-in employee record',
            self::EMPLOYEES_CREATE => 'Create employee records',
            self::EMPLOYEES_UPDATE => 'Update employee records',
            self::EMPLOYEES_DELETE => 'Delete employee records',
            self::ATTENDANCE_VIEW => 'View attendance',
            self::ATTENDANCE_APPROVE => 'Approve attendance',
            self::LEAVE_VIEW => 'View leave',
            self::LEAVE_APPROVE => 'Approve leave',
            self::PAYROLL_VIEW => 'View payroll',
            self::PAYROLL_VIEW_OWN => 'View the signed-in employee payslips and tax draft',
            self::PAYROLL_PROCESS => 'Process payroll',
            self::DOCUMENTS_VIEW => 'View documents',
            self::DOCUMENTS_GENERATE => 'Generate documents',
            self::DOCUMENTS_IDENTITY => 'View sensitive identity documents',
            self::RECRUITMENT_VIEW => 'View recruitment',
            self::RECRUITMENT_MANAGE => 'Manage recruitment',
            self::RECRUITMENT_REQUISITIONS_APPROVE => 'Approve or reject job requisitions',
            self::RECRUITMENT_OFFERS_APPROVE => 'Approve or reject offers',
            self::RECRUITMENT_JOINING_CONFIRM => 'Confirm a candidate has joined and create the employee',
            self::RECRUITMENT_REWARDS => 'Configure and decide referral rewards without creating a payment',
            self::ONBOARDING_MANAGE => 'Manage joining checklists and onboarding documents',
            self::ASSETS_VIEW => 'View assets',
            self::ASSETS_MANAGE => 'Manage assets',
            self::EXPENSES_VIEW => 'View expenses',
            self::EXPENSES_SUBMIT => 'Submit expenses',
            self::EXPENSES_APPROVE => 'Approve expenses',
            self::HELPDESK_VIEW => 'View helpdesk tickets',
            self::HELPDESK_MANAGE => 'Manage helpdesk tickets',
            self::PERFORMANCE_VIEW => 'View performance reviews',
            self::PERFORMANCE_MANAGE => 'Manage performance reviews',
            self::ENGAGEMENT_VIEW => 'View engagement',
            self::ENGAGEMENT_MANAGE => 'Manage engagement',
            self::NOTIFICATIONS_MANAGE => 'Configure notification providers',
            self::REPORTS_VIEW => 'View reports',
            self::REPORTS_EXPORT => 'Export reports',
            self::BILLING_VIEW => 'View the active business subscription and usage',
            self::BILLING_MANAGE => 'Approve organizations and manage subscription plans',
            self::AI_USE => 'Use the tenant-scoped HR assistant',
            self::INTEGRATIONS_MANAGE => 'Configure biometric, payroll, and accounting integrations',
            self::WORKFLOWS_MANAGE => 'Build and run workflow notifications',
        ];
    }
}
