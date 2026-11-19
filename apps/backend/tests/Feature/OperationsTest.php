<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Employee;
use App\Models\Organization;
use App\Models\OrganizationAccess;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class OperationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_reviews_assets_and_reminders_stay_inside_the_business(): void
    {
        [$organization, $hr] = $this->member('hr-admin');
        $staffUser = User::factory()->create();
        OrganizationAccess::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $staffUser->id,
            'role_id' => Role::query()->where('slug', 'employee')->value('id'),
        ]);
        $staff = Employee::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $staffUser->id,
            'status' => 'active',
            'joined_on' => '2020-11-01',
            'date_of_birth' => '1992-11-02',
        ]);
        $other = Organization::factory()->create();
        $foreign = Asset::unguarded(fn () => Asset::query()->create([
            'organization_id' => $other->id, 'asset_code' => 'X-1', 'category' => 'mobile', 'name' => 'Phone', 'condition' => 'good', 'status' => 'in_stock',
        ]));
        $this->actingAs($hr);
        $kpi = $this->postJson('/api/v1/kpis', ['name' => 'Cases closed', 'unit' => 'count', 'weight' => 20], $this->header($organization))->assertCreated()->json('data.id');
        $this->postJson('/api/v1/goals', [
            'scope' => 'employee', 'employee_id' => $staff->id, 'kpi_id' => $kpi, 'title' => 'Monthly cases', 'period' => 'monthly', 'period_on' => '2026-11-01', 'target' => '40',
        ], $this->header($organization))->assertCreated();
        $review = $this->postJson('/api/v1/performance-reviews', [
            'employee_id' => $staff->id, 'cycle' => 'quarterly', 'period_on' => '2026-10-01',
        ], $this->header($organization))->assertCreated()->json('data.id');
        $this->actingAs($staffUser);
        $this->postJson('/api/v1/performance-reviews/'.$review.'/self', ['score' => 4, 'comments' => 'Met the quarterly target.'], $this->header($organization))->assertOk();
        $this->actingAs($hr);
        $this->postJson('/api/v1/performance-reviews/'.$review.'/complete', [
            'score' => 5, 'comments' => 'Ready for a wider role.', 'promotion_recommended' => true,
        ], $this->header($organization))->assertOk()->assertJsonPath('data.manager_score', 5);
        $this->getJson('/api/v1/performance-reviews', $this->header($organization))->assertOk()->assertJsonPath('data.0.promotion_recommended', true);

        $asset = $this->postJson('/api/v1/assets', [
            'asset_code' => 'LT-100', 'category' => 'laptop', 'name' => 'Notebook', 'condition' => 'good',
        ], $this->header($organization))->assertCreated()->json('data.id');
        $allocation = $this->postJson('/api/v1/assets/'.$asset.'/assign', [
            'employee_id' => $staff->id, 'allocated_on' => '2026-11-17',
        ], $this->header($organization))->assertCreated()->json('data.id');
        $this->postJson('/api/v1/asset-allocations/'.$allocation.'/return', [
            'returned_on' => '2026-11-20', 'condition' => 'fair',
        ], $this->header($organization))->assertOk();
        $this->getJson('/api/v1/employees/'.$staff->id.'/assets', $this->header($organization))
            ->assertOk()->assertJsonPath('data.0.return_condition', 'fair');

        $reminders = $this->getJson('/api/v1/reminders', $this->header($organization))->assertOk();
        $this->assertSame($staff->fullName(), $reminders->json('data.birthdays.0.name'));
        $this->assertSame('02 Nov', $reminders->json('data.birthdays.0.day'));
        $this->assertStringNotContainsString('1992', json_encode($reminders->json('data.birthdays')));

        $this->postJson('/api/v1/assets/'.$foreign->id.'/assign', [
            'employee_id' => $staff->id, 'allocated_on' => '2026-11-17',
        ], $this->header($organization))->assertNotFound();
    }

    public function test_an_expense_needs_the_manager_and_a_different_finance_approver(): void
    {
        [$organization, $hr] = $this->member('hr-admin');
        $managerUser = User::factory()->create();
        $staffUser = User::factory()->create();
        $finance = User::factory()->create();
        foreach ([['department-manager', $managerUser], ['employee', $staffUser], ['accountant', $finance]] as [$role, $user]) {
            OrganizationAccess::query()->create([
                'organization_id' => $organization->id,
                'user_id' => $user->id,
                'role_id' => Role::query()->where('slug', $role)->value('id'),
            ]);
        }
        $manager = Employee::factory()->create(['organization_id' => $organization->id, 'user_id' => $managerUser->id, 'status' => 'active']);
        $staff = Employee::factory()->create(['organization_id' => $organization->id, 'user_id' => $staffUser->id, 'manager_id' => $manager->id, 'status' => 'active']);
        $this->actingAs($hr);
        $category = $this->postJson('/api/v1/expense-categories', ['name' => 'Travel'], $this->header($organization))->assertCreated()->json('data.id');
        $this->actingAs($staffUser);
        $claim = $this->postJson('/api/v1/expenses', [
            'expense_category_id' => $category, 'amount' => 640, 'incurred_on' => '2026-11-10', 'description' => 'Client visit',
        ], $this->header($organization))->assertCreated()->json('data.id');
        $this->post('/api/v1/expenses/'.$claim.'/receipt', [
            'receipt' => UploadedFile::fake()->create('receipt.pdf', 10, 'application/pdf'),
        ], $this->header($organization))->assertOk();
        $this->actingAs($managerUser);
        $this->postJson('/api/v1/expenses/'.$claim.'/manager-approval', [], $this->header($organization))->assertOk();
        $this->postJson('/api/v1/expenses/'.$claim.'/finance-approval', [], $this->header($organization))->assertUnprocessable();
        $this->actingAs($finance);
        $this->postJson('/api/v1/expenses/'.$claim.'/finance-approval', [], $this->header($organization))->assertOk()->assertJsonPath('data.status', 'approved');
        $this->actingAs($hr);
        $this->postJson('/api/v1/expenses/'.$claim.'/reimburse', [], $this->header($organization))->assertOk()->assertJsonPath('data.status', 'reimbursed');

        $this->actingAs($staffUser);
        $second = $this->postJson('/api/v1/expenses', [
            'expense_category_id' => $category, 'amount' => 80, 'incurred_on' => '2026-11-11', 'description' => 'Local travel',
        ], $this->header($organization))->assertCreated()->json('data.id');
        $this->actingAs($managerUser);
        $this->postJson('/api/v1/expenses/'.$second.'/reject', ['reason' => 'Need a clearer description'], $this->header($organization))->assertOk();
        $this->actingAs($staffUser);
        $this->postJson('/api/v1/expenses/'.$second.'/resubmit', [], $this->header($organization))->assertOk()->assertJsonPath('data.status', 'submitted');
    }

    public function test_helpdesk_uses_the_sla_and_anonymous_feedback_hides_the_author(): void
    {
        [$organization, $hr] = $this->member('hr-admin');
        $staffUser = User::factory()->create();
        OrganizationAccess::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $staffUser->id,
            'role_id' => Role::query()->where('slug', 'employee')->value('id'),
        ]);
        Employee::factory()->create(['organization_id' => $organization->id, 'user_id' => $staffUser->id, 'status' => 'active']);
        $this->actingAs($hr);
        $category = $this->postJson('/api/v1/ticket-categories', ['name' => 'Payroll query'], $this->header($organization))->assertCreated()->json('data.id');
        $this->postJson('/api/v1/sla-policies', ['priority' => 'high', 'response_hours' => 4], $this->header($organization))->assertOk();
        $this->actingAs($staffUser);
        $ticket = $this->postJson('/api/v1/tickets', [
            'ticket_category_id' => $category, 'subject' => 'Payslip question', 'priority' => 'high',
        ], $this->header($organization))->assertCreated();
        $this->assertNotNull($ticket->json('data.due_at'));
        $this->postJson('/api/v1/tickets/'.$ticket->json('data.id').'/comments', ['body' => 'Which month?'], $this->header($organization))->assertCreated();
        $this->postJson('/api/v1/feedback', ['body' => 'The floor is noisy.', 'anonymous' => true], $this->header($organization))->assertCreated();
        $this->getJson('/api/v1/feedback', $this->header($organization))->assertOk()->assertJsonPath('data.0.employee', null);
        $this->actingAs($hr);
        $this->postJson('/api/v1/posts', ['kind' => 'announcement', 'title' => 'Office note', 'body' => 'The lobby closes at 7.'], $this->header($organization))->assertCreated();
        $this->getJson('/api/v1/feedback', $this->header($organization))->assertOk()->assertJsonPath('data.0.employee', Employee::query()->where('user_id', $staffUser->id)->first()->fullName());
        $this->postJson('/api/v1/tickets/'.$ticket->json('data.id').'/resolve', [], $this->header($organization))->assertOk()->assertJsonPath('data.status', 'resolved');
        $this->getJson('/api/v1/tickets/'.$ticket->json('data.id').'/history', $this->header($organization))->assertOk()->assertJsonPath('data.0.body', 'Which month?');
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
