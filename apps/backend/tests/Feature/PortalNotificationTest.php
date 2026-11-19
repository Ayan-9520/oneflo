<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\Department;
use App\Models\Employee;
use App\Models\LegalEntity;
use App\Models\NotificationDelivery;
use App\Models\Organization;
use App\Models\OrganizationAccess;
use App\Models\OrganizationAccessScope;
use App\Models\PayrollLine;
use App\Models\PayrollRun;
use App\Models\Role;
use App\Models\User;
use App\Modules\Notifications\NotificationEvents;
use App\Modules\Notifications\NotificationService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PortalNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_the_employee_and_manager_portals_stay_inside_the_assigned_scope(): void
    {
        [$organization, $hr] = $this->member('hr-admin');
        $department = Department::unguarded(fn () => Department::query()->create(['organization_id' => $organization->id, 'name' => 'Service']));
        $otherDepartment = Department::unguarded(fn () => Department::query()->create(['organization_id' => $organization->id, 'name' => 'Stores']));
        $manager = User::factory()->create();
        $access = OrganizationAccess::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $manager->id,
            'role_id' => Role::query()->where('slug', 'department-manager')->value('id'),
        ]);
        OrganizationAccessScope::query()->create(['organization_access_id' => $access->id, 'scope_type' => 'department', 'scope_id' => $department->id]);
        $staffUser = User::factory()->create();
        OrganizationAccess::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $staffUser->id,
            'role_id' => Role::query()->where('slug', 'employee')->value('id'),
        ]);
        $entity = LegalEntity::unguarded(fn () => LegalEntity::query()->create(['organization_id' => $organization->id, 'legal_name' => 'Northwind Engineering']));
        $staff = Employee::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $staffUser->id,
            'department_id' => $department->id,
            'legal_entity_id' => $entity->id,
            'manager_id' => null,
            'phone' => '9000000000',
        ]);
        $outsider = Employee::factory()->create(['organization_id' => $organization->id, 'department_id' => $otherDepartment->id, 'legal_entity_id' => $entity->id]);
        $other = Organization::factory()->create();
        $foreign = Employee::factory()->create(['organization_id' => $other->id]);
        $run = PayrollRun::unguarded(fn () => PayrollRun::query()->create([
            'organization_id' => $organization->id, 'legal_entity_id' => $entity->id, 'year' => 2026, 'month' => 4, 'status' => 'locked', 'warnings' => [],
        ]));
        PayrollRun::unguarded(fn () => PayrollRun::query()->create([
            'organization_id' => $organization->id, 'legal_entity_id' => $entity->id, 'year' => 2026, 'month' => 5, 'status' => 'preview', 'warnings' => [],
        ]));
        $line = PayrollLine::unguarded(fn () => PayrollLine::query()->create([
            'organization_id' => $organization->id, 'payroll_run_id' => $run->id, 'employee_id' => $staff->id,
            'earnings' => [], 'deductions' => ['tds' => 10000], 'employer' => [], 'gross_paise' => 4000000, 'deduction_paise' => 10000, 'net_paise' => 3990000,
        ]));
        PayrollLine::unguarded(fn () => PayrollLine::query()->create([
            'organization_id' => $organization->id, 'payroll_run_id' => $run->id, 'employee_id' => $outsider->id,
            'earnings' => [], 'deductions' => ['tds' => 20000], 'employer' => [], 'gross_paise' => 100, 'deduction_paise' => 20000, 'net_paise' => 100,
        ]));

        $this->actingAs($staffUser);
        $me = $this->getJson('/api/v1/me', $this->header($organization))->assertOk();
        $this->assertSame($staff->fullName(), $me->json('data.profile.full_name'));
        $this->assertSame($line->id, $me->json('data.payslips.0.id'));
        $this->assertCount(1, $me->json('data.payslips'));
        $this->getJson('/api/v1/me/tax-documents?financial_year=2026', $this->header($organization))
            ->assertOk()
            ->assertJsonPath('data.part_a.employees.0.name', $staff->fullName())
            ->assertJsonMissing(['name' => $outsider->fullName()]);
        $this->getJson('/api/v1/manager', $this->header($organization))->assertForbidden();
        $this->getJson('/api/v1/payroll/lines/'.$line->id.'/payslip', $this->header($organization))->assertOk();

        $this->actingAs($manager);
        $team = $this->getJson('/api/v1/manager', $this->header($organization))->assertOk();
        $this->assertSame([$staff->id], $team->json('data.employees.*.id'));
        $this->getJson('/api/v1/manager/employees/'.$staff->id, $this->header($organization))->assertOk()->assertJsonPath('data.full_name', $staff->fullName());
        $this->getJson('/api/v1/manager/employees/'.$outsider->id, $this->header($organization))->assertNotFound();
        $this->getJson('/api/v1/manager/employees/'.$foreign->id, $this->header($organization))->assertNotFound();
        $this->actingAs($hr);
        $this->assertNotSame($staff->id, $foreign->id);
    }

    public function test_notifications_are_delivered_once_and_skip_unconfigured_channels(): void
    {
        [$organization, $hr] = $this->member('hr-admin');
        $staff = User::factory()->create();
        OrganizationAccess::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $staff->id,
            'role_id' => Role::query()->where('slug', 'employee')->value('id'),
        ]);
        Employee::factory()->create(['organization_id' => $organization->id, 'user_id' => $staff->id, 'phone' => '9000000001']);
        Mail::fake();
        $notifier = app(NotificationService::class);
        $notifier->publish($organization, [$hr], NotificationEvents::LEAVE_APPLIED, 'leave:1', 'Leave application', 'A leave application is waiting.');
        $notifier->publish($organization, [$hr], NotificationEvents::LEAVE_APPLIED, 'leave:1', 'Leave application', 'A leave application is waiting.');
        $email = NotificationDelivery::query()->where('channel', 'email')->orderByDesc('id')->first();
        $this->assertNotNull($email);
        $this->assertSame('sent', $email->status, (string) $email->detail);
        $this->assertSame(1, AppNotification::query()->withoutGlobalScopes()->count());

        $this->actingAs($hr);
        $this->putJson('/api/v1/notification-preferences', [
            'event' => NotificationEvents::LEAVE_APPLIED,
            'in_app' => true,
            'email' => false,
            'sms' => true,
            'whatsapp' => true,
        ], $this->header($organization))->assertOk();
        $before = NotificationDelivery::query()->where('channel', 'email')->where('status', 'sent')->count();
        $notifier->publish($organization, [$hr], NotificationEvents::LEAVE_APPLIED, 'leave:2', 'Leave application', 'Another application is waiting.');
        $this->assertSame($before, NotificationDelivery::query()->where('channel', 'email')->where('status', 'sent')->count());
        $this->assertSame('unconfigured', NotificationDelivery::query()->where('channel', 'sms')->orderByDesc('id')->value('status'));
        $this->assertSame('unconfigured', NotificationDelivery::query()->where('channel', 'whatsapp')->orderByDesc('id')->value('status'));

        $this->putJson('/api/v1/notification-providers', [
            'channel' => 'sms',
            'endpoint' => 'http://sms.example.test/send',
            'sender' => 'ONEFLO',
            'secret' => 'secret-token',
            'enabled' => true,
        ], $this->header($organization))->assertUnprocessable();
        $this->putJson('/api/v1/notification-providers', [
            'channel' => 'sms',
            'endpoint' => 'https://sms.example.test/send',
            'sender' => 'ONEFLO',
            'secret' => 'secret-token',
            'enabled' => true,
        ], $this->header($organization))->assertOk();
        $this->actingAs($staff);
        $this->putJson('/api/v1/notification-preferences', [
            'event' => NotificationEvents::PAYSLIP_AVAILABLE,
            'in_app' => true,
            'email' => false,
            'sms' => true,
            'whatsapp' => false,
        ], $this->header($organization))->assertOk();
        $this->putJson('/api/v1/notification-providers', [
            'channel' => 'whatsapp',
            'endpoint' => 'https://sms.example.test/send',
            'sender' => 'ONEFLO',
            'secret' => 'secret-token',
            'enabled' => true,
        ], $this->header($organization))->assertForbidden();
        Http::fake(['https://sms.example.test/*' => Http::response(['accepted' => true], 202)]);
        $notifier->publish($organization, [$staff], NotificationEvents::PAYSLIP_AVAILABLE, 'payslip:9', 'Payslip available', 'Your payslip is ready.');
        $notifier->publish($organization, [$staff], NotificationEvents::PAYSLIP_AVAILABLE, 'payslip:9', 'Payslip available', 'Your payslip is ready.');
        Http::assertSentCount(1);
        $this->getJson('/api/v1/notifications', $this->header($organization))->assertOk()->assertJsonPath('data.0.title', 'Payslip available');
    }

    /**
     * @return array{0: Organization, 1: User}
     */
    private function member(string $roleSlug): array
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create();
        OrganizationAccess::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'role_id' => Role::query()->where('slug', $roleSlug)->value('id'),
        ]);

        return [$organization, $user];
    }

    /**
     * @return array<string, string>
     */
    private function header(Organization $organization): array
    {
        return ['X-Organization-Id' => (string) $organization->id];
    }
}
