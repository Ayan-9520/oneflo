<?php

namespace Tests\Feature;

use App\Models\AttendanceDay;
use App\Models\Employee;
use App\Models\Organization;
use App\Models\OrganizationAccess;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceLeaveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_punches_calculate_late_half_days_missing_punches_and_weekly_offs(): void
    {
        [$organization, $hr] = $this->member('hr-admin');
        $worker = User::factory()->create();
        OrganizationAccess::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $worker->id,
            'role_id' => Role::query()->where('slug', 'employee')->value('id'),
        ]);
        $employee = Employee::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $worker->id,
        ]);
        $this->actingAs($hr);
        $shiftId = $this->postJson('/api/v1/shifts', [
            'name' => 'General',
            'starts_at' => '09:00',
            'ends_at' => '18:00',
            'grace_minutes' => 10,
            'early_grace_minutes' => 0,
            'overtime_threshold_minutes' => 30,
            'weekly_offs' => [7],
        ], $this->header($organization))->assertCreated()->json('data.id');
        $this->postJson('/api/v1/shift-assignments', [
            'employee_id' => $employee->id,
            'shift_id' => $shiftId,
            'effective_on' => '2026-11-01',
        ], $this->header($organization))->assertCreated();

        $this->actingAs($worker);
        $this->postJson('/api/v1/attendance/punches', [
            'kind' => 'in',
            'work_mode' => 'office',
            'work_date' => '2026-11-17',
            'punched_at' => '2026-11-17 09:25:00',
            'latitude' => 18.5,
        ], $this->header($organization))->assertUnprocessable();
        $this->postJson('/api/v1/attendance/punches', [
            'kind' => 'in',
            'work_mode' => 'remote',
            'work_date' => '2026-11-17',
            'punched_at' => '2026-11-17 09:25:00',
        ], $this->header($organization))->assertCreated()
            ->assertJsonPath('data.status', 'missing_punch');
        $this->postJson('/api/v1/attendance/punches', [
            'kind' => 'out',
            'work_mode' => 'remote',
            'work_date' => '2026-11-17',
            'punched_at' => '2026-11-17 18:00:00',
        ], $this->header($organization))->assertCreated()
            ->assertJsonPath('data.late_minutes', 25)
            ->assertJsonPath('data.status', 'present')
            ->assertJsonPath('data.work_mode', 'remote');

        $this->postJson('/api/v1/attendance/punches', [
            'kind' => 'in',
            'work_mode' => 'field',
            'work_date' => '2026-11-18',
            'punched_at' => '2026-11-18 09:00:00',
        ], $this->header($organization))->assertCreated();
        $this->postJson('/api/v1/attendance/punches', [
            'kind' => 'out',
            'work_mode' => 'field',
            'work_date' => '2026-11-18',
            'punched_at' => '2026-11-18 12:00:00',
        ], $this->header($organization))->assertCreated()->assertJsonPath('data.status', 'half_day');

        $this->actingAs($hr);
        $month = $this->getJson('/api/v1/attendance/month?employee_id='.$employee->id.'&year=2026&month=11', $this->header($organization))
            ->assertOk()
            ->json('data');
        $sunday = collect($month)->firstWhere('work_date', '2026-11-15');
        $this->assertSame('weekly_off', $sunday['status']);
    }

    public function test_a_rotating_shift_changes_with_the_cycle(): void
    {
        [$organization, $hr] = $this->member('hr-admin');
        $employee = Employee::factory()->create(['organization_id' => $organization->id]);
        $this->actingAs($hr);
        $morning = $this->shift($organization, 'Morning', '06:00', '14:00');
        $evening = $this->shift($organization, 'Evening', '14:00', '22:00');
        $rotation = $this->postJson('/api/v1/shift-rotations', [
            'name' => 'Alternate days',
            'slots' => [
                ['shift_id' => $morning, 'day_count' => 1],
                ['shift_id' => $evening, 'day_count' => 1],
            ],
        ], $this->header($organization))->assertCreated()->json('data.id');
        $this->postJson('/api/v1/shift-rotation-assignments', [
            'employee_id' => $employee->id,
            'shift_rotation_id' => $rotation,
            'starts_on' => '2026-11-02',
        ], $this->header($organization))->assertCreated();

        $calendar = $this->getJson('/api/v1/attendance/calendar?employee_id='.$employee->id.'&from=2026-11-02&to=2026-11-03', $this->header($organization))
            ->assertOk()
            ->json('data');
        $this->assertSame('Morning', $calendar[0]['shift']['name']);
        $this->assertSame('Evening', $calendar[1]['shift']['name']);
    }

    public function test_attendance_approval_follows_manager_then_hr_and_stays_inside_the_business(): void
    {
        [$organization, $hr] = $this->member('hr-admin');
        $managerUser = User::factory()->create();
        OrganizationAccess::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $managerUser->id,
            'role_id' => Role::query()->where('slug', 'department-manager')->value('id'),
        ]);
        $manager = Employee::factory()->create(['organization_id' => $organization->id, 'user_id' => $managerUser->id]);
        $worker = User::factory()->create();
        OrganizationAccess::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $worker->id,
            'role_id' => Role::query()->where('slug', 'employee')->value('id'),
        ]);
        $employee = Employee::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $worker->id,
            'manager_id' => $manager->id,
        ]);
        $other = Organization::factory()->create();
        $foreign = Employee::factory()->create(['organization_id' => $other->id]);

        $this->actingAs($worker);
        $requestId = $this->postJson('/api/v1/attendance/requests', [
            'employee_id' => $employee->id,
            'work_date' => '2026-11-20',
            'type' => 'regularization',
            'requested_in' => '09:00',
            'requested_out' => '18:00',
            'reason' => 'Missed punch',
        ], $this->header($organization))->assertCreated()->assertJsonPath('data.current_step', 'manager')->json('data.id');
        $this->postJson('/api/v1/attendance/requests/'.$requestId.'/decision', ['approve' => true], $this->header($organization))->assertForbidden();

        $this->actingAs($hr);
        $this->getJson('/api/v1/attendance/month?employee_id='.$foreign->id.'&year=2026&month=11', $this->header($organization))->assertNotFound();
        $this->postJson('/api/v1/attendance/requests/'.$requestId.'/decision', ['approve' => true], $this->header($organization))->assertForbidden();

        $this->actingAs($managerUser);
        $this->postJson('/api/v1/attendance/requests/'.$requestId.'/decision', ['approve' => true], $this->header($organization))
            ->assertOk()
            ->assertJsonPath('data.current_step', 'hr')
            ->assertJsonPath('data.status', 'pending');

        $this->actingAs($hr);
        $this->postJson('/api/v1/attendance/requests/'.$requestId.'/decision', ['approve' => true], $this->header($organization))
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');
        $this->assertSame('present', AttendanceDay::query()->where('employee_id', $employee->id)->whereDate('work_date', '2026-11-20')->value('status'));
    }

    public function test_leave_accrual_balance_carry_forward_and_approval_restore_the_balance(): void
    {
        [$organization, $hr] = $this->member('hr-admin');
        $managerUser = User::factory()->create();
        OrganizationAccess::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $managerUser->id,
            'role_id' => Role::query()->where('slug', 'department-manager')->value('id'),
        ]);
        $manager = Employee::factory()->create(['organization_id' => $organization->id, 'user_id' => $managerUser->id]);
        $worker = User::factory()->create();
        OrganizationAccess::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $worker->id,
            'role_id' => Role::query()->where('slug', 'employee')->value('id'),
        ]);
        $employee = Employee::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $worker->id,
            'manager_id' => $manager->id,
            'status' => 'active',
        ]);
        $this->actingAs($hr);
        $casual = $this->postJson('/api/v1/leave/types', ['name' => 'Casual leave', 'code' => 'CL', 'paid' => true], $this->header($organization))
            ->assertCreated()->json('data.id');
        $unpaid = $this->postJson('/api/v1/leave/types', ['name' => 'Unpaid leave', 'code' => 'UL', 'paid' => false], $this->header($organization))
            ->assertCreated()->json('data.id');
        $this->postJson('/api/v1/leave/policies', [
            'leave_type_id' => $casual,
            'year' => 2026,
            'annual_days' => 12,
            'accrual_days' => 1,
            'carry_forward_max' => 5,
            'encashment_allowed' => true,
            'encashment_max' => 2,
            'approval_steps' => ['manager', 'hr'],
        ], $this->header($organization))->assertCreated();
        $this->postJson('/api/v1/leave/policies', [
            'leave_type_id' => $unpaid,
            'year' => 2026,
            'annual_days' => 0,
            'accrual_days' => 0,
            'carry_forward_max' => 0,
            'encashment_allowed' => false,
            'encashment_max' => 0,
            'approval_steps' => ['hr'],
        ], $this->header($organization))->assertCreated();
        $this->postJson('/api/v1/leave/accruals', ['year' => 2026, 'month' => 11], $this->header($organization))->assertOk();
        $this->postJson('/api/v1/leave/accruals', ['year' => 2026, 'month' => 11], $this->header($organization))->assertOk()->assertJsonPath('data.entries', 0);

        $this->actingAs($worker);
        $applicationId = $this->postJson('/api/v1/leave/applications', [
            'employee_id' => $employee->id,
            'leave_type_id' => $casual,
            'starts_on' => '2026-11-23',
            'ends_on' => '2026-11-23',
            'reason' => 'Personal',
        ], $this->header($organization))->assertCreated()->assertJsonPath('data.days', '1.00')->json('data.id');
        $this->postJson('/api/v1/leave/applications', [
            'employee_id' => $employee->id,
            'leave_type_id' => $casual,
            'starts_on' => '2026-11-24',
            'ends_on' => '2026-11-24',
        ], $this->header($organization))->assertUnprocessable();
        $this->postJson('/api/v1/leave/applications', [
            'employee_id' => $employee->id,
            'leave_type_id' => $unpaid,
            'starts_on' => '2026-11-24',
            'ends_on' => '2026-11-24',
        ], $this->header($organization))->assertCreated();

        $this->actingAs($managerUser);
        $this->postJson('/api/v1/leave/applications/'.$applicationId.'/decision', ['approve' => false], $this->header($organization))
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected');

        $this->actingAs($hr);
        $available = collect($this->getJson('/api/v1/leave/balances?year=2026', $this->header($organization))->assertOk()->json('data'))
            ->firstWhere('leave_type_id', $casual);
        $this->assertSame('1.00', $available['available_days']);
        $this->postJson('/api/v1/leave/carry-forward', ['from_year' => 2026, 'to_year' => 2027], $this->header($organization))->assertOk();
        $this->assertDatabaseHas('leave_balances', [
            'employee_id' => $employee->id,
            'leave_type_id' => $casual,
            'year' => 2027,
            'carried_days' => 1,
        ]);
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

    private function shift(Organization $organization, string $name, string $start, string $end): int
    {
        return $this->postJson('/api/v1/shifts', [
            'name' => $name,
            'starts_at' => $start,
            'ends_at' => $end,
            'grace_minutes' => 0,
            'early_grace_minutes' => 0,
            'overtime_threshold_minutes' => 0,
            'weekly_offs' => [],
        ], $this->header($organization))->assertCreated()->json('data.id');
    }

    /**
     * @return array<string, string>
     */
    private function header(Organization $organization): array
    {
        return ['X-Organization-Id' => (string) $organization->id];
    }
}
