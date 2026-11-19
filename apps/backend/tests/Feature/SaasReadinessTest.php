<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\Employee;
use App\Models\IntegrationDelivery;
use App\Models\IssuedDocument;
use App\Models\LegalEntity;
use App\Models\Organization;
use App\Models\OrganizationAccess;
use App\Models\PayrollRun;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use App\Modules\Saas\IntegrationDispatcher;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SaasReadinessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_registration_approval_limits_billing_and_deactivation_are_enforced(): void
    {
        $applicant = User::factory()->create();
        $other = User::factory()->create();
        $platform = User::factory()->create();
        $platform->platformRoles()->attach(Role::query()->where('slug', 'super-admin')->value('id'));

        $this->actingAs($applicant);
        $registration = $this->postJson('/api/v1/organization-registrations', ['name' => 'Harbor Workspace'])->assertCreated();
        $organizationId = $registration->json('data.organization_id');
        $organization = Organization::query()->findOrFail($organizationId);
        $this->assertSame('pending', $organization->status);
        $this->getJson('/api/v1/roles', $this->header($organization))->assertForbidden();

        $this->actingAs($other);
        $this->postJson('/api/v1/organization-registrations', ['name' => 'Other Workspace'])->assertCreated();
        $this->actingAs($applicant);
        $own = $this->getJson('/api/v1/organization-registrations')->assertOk();
        $this->assertCount(1, $own->json('data'));
        $this->assertStringNotContainsString('Other Workspace', json_encode($own->json()));

        $this->actingAs($platform);
        $this->getJson('/api/v1/organization-registrations')->assertOk()->assertJsonFragment(['organization' => 'Other Workspace']);
        $plan = $this->postJson('/api/v1/subscription-plans', [
            'code' => 'attendance-trial',
            'name' => 'Attendance trial',
            'interval' => 'monthly',
            'trial_days' => 14,
            'currency' => 'INR',
            'employee_limit' => 1,
            'features' => ['attendance'],
            'status' => 'active',
        ])->assertCreated();
        $this->postJson('/api/v1/organization-registrations/'.$registration->json('data.id').'/decision', [
            'approve' => true,
            'plan_id' => $plan->json('data.id'),
        ])->assertOk()->assertJsonPath('data.subscription_status', 'trial');

        $this->actingAs($applicant);
        $this->getJson('/api/v1/payroll/runs', $this->header($organization))->assertForbidden();
        $this->getJson('/api/v1/attendance/summary', $this->header($organization))->assertOk();
        Employee::factory()->create(['organization_id' => $organization->id, 'employee_code' => 'EMP-LIMIT']);
        $this->postJson('/api/v1/employees', [
            'employee_code' => 'EMP-OVER',
            'given_name' => 'Extra',
            'family_name' => 'Person',
        ], $this->header($organization))->assertStatus(422);
        Subscription::withoutGlobalScopes()->where('organization_id', $organization->id)->update([
            'trial_ends_on' => now()->subDay()->toDateString(),
        ]);
        $this->getJson('/api/v1/attendance/summary', $this->header($organization))->assertForbidden();
        $this->assertSame('expired', Subscription::withoutGlobalScopes()->where('organization_id', $organization->id)->value('status'));

        $this->actingAs($platform);
        $this->patchJson('/api/v1/subscription-plans/'.$plan->json('data.id'), [
            'code' => 'attendance-trial',
            'name' => 'Attendance trial',
            'interval' => 'monthly',
            'trial_days' => 14,
            'price_rupees' => '2500.50',
            'currency' => 'INR',
            'employee_limit' => 1,
            'features' => ['attendance'],
            'status' => 'active',
        ])->assertOk();
        $this->actingAs($applicant);
        $renewed = $this->postJson('/api/v1/subscription/renew', [], $this->header($organization))->assertCreated();
        $this->assertSame('2500.50', $renewed->json('data.amount_rupees'));
        $this->getJson('/api/v1/billing-invoices', $this->header($organization))->assertOk()->assertJsonPath('data.0.amount_rupees', '2500.50');
        $this->getJson('/api/v1/attendance/summary', $this->header($organization))->assertOk();
        $this->getJson('/api/v1/payroll/runs', $this->header($organization))->assertForbidden();

        $this->actingAs($platform);
        $this->patchJson('/api/v1/organizations/'.$organization->id.'/activation', ['status' => 'inactive'])->assertOk();
        $this->actingAs($applicant);
        $this->getJson('/api/v1/subscription', $this->header($organization))->assertForbidden();
        $this->actingAs($platform);
        $this->patchJson('/api/v1/organizations/'.$organization->id.'/activation', ['status' => 'active'])->assertOk();
        $this->actingAs($applicant);
        $this->getJson('/api/v1/usage', $this->header($organization))->assertOk()->assertJsonPath('data.employees', 1);
        $this->getJson('/api/v1/tenant-onboarding', $this->header($organization))->assertOk()->assertJsonFragment(['key' => 'employee', 'complete' => true]);
        $this->postJson('/api/v1/subscription/renew', [], $this->header($organization->fresh() ? $organization : $organization))->assertCreated();
    }

    public function test_integrations_and_ai_cannot_cross_organizations(): void
    {
        [$organization, $admin] = $this->member('business-admin');
        $other = Organization::factory()->create();
        $foreign = Employee::factory()->create(['organization_id' => $other->id, 'employee_code' => 'FOREIGN-900']);
        $entity = LegalEntity::unguarded(fn () => LegalEntity::query()->create(['organization_id' => $organization->id, 'legal_name' => 'Harbor Legal']));
        $foreignEntity = LegalEntity::unguarded(fn () => LegalEntity::query()->create(['organization_id' => $other->id, 'legal_name' => 'Foreign Legal']));
        IssuedDocument::unguarded(fn () => IssuedDocument::query()->create([
            'organization_id' => $organization->id, 'legal_entity_id' => $entity->id, 'document_type' => 'general_letter',
            'number' => 'OWN-DOC', 'version' => 1, 'issued_on' => now()->toDateString(), 'html' => 'Harbor policy', 'lines' => [], 'oneflo_attribution' => false,
        ]));
        IssuedDocument::unguarded(fn () => IssuedDocument::query()->create([
            'organization_id' => $other->id, 'legal_entity_id' => $foreignEntity->id, 'document_type' => 'general_letter',
            'number' => 'FOREIGN-DOC', 'version' => 1, 'issued_on' => now()->toDateString(), 'html' => 'Secret policy', 'lines' => [], 'oneflo_attribution' => false,
        ]));
        $outsider = User::factory()->create();
        OrganizationAccess::query()->create([
            'organization_id' => $other->id,
            'user_id' => $outsider->id,
            'role_id' => Role::query()->where('slug', 'business-admin')->value('id'),
        ]);

        $this->actingAs($admin);
        $this->postJson('/api/v1/ai/ask', ['question' => 'How many people left?'], $this->header($organization))->assertStatus(422);
        Http::fake(['https://ai.example.test/*' => Http::response(['answer' => 'Use the attrition count.'], 200)]);
        $this->putJson('/api/v1/ai/provider', [
            'endpoint' => 'https://ai.example.test/answer',
            'secret' => 'ai-secret',
            'enabled' => true,
        ], $this->header($organization))->assertCreated();
        $this->postJson('/api/v1/ai/ask', ['question' => 'How many people left?'], $this->header($organization))
            ->assertOk()
            ->assertJsonPath('data.mode', 'provider');
        Http::assertSent(function ($request) use ($organization, $foreign): bool {
            $body = $request->body();

            return $request->url() === 'https://ai.example.test/answer'
                && str_contains($body, '"organization_id":'.$organization->id)
                && ! str_contains($body, $foreign->employee_code);
        });
        $search = $this->getJson('/api/v1/ai/documents?q=DOC', $this->header($organization))->assertOk();
        $this->assertSame('stored-text', $search->json('data.mode'));
        $this->assertSame('OWN-DOC', $search->json('data.matches.0.number'));
        $this->assertStringNotContainsString('FOREIGN-DOC', json_encode($search->json()));

        $this->postJson('/api/v1/integration-providers', [
            'kind' => 'biometric',
            'endpoint' => 'https://biometric.example.test/push',
            'secret' => 'device-secret',
            'enabled' => true,
        ], $this->header($organization))->assertCreated();
        $this->postJson('/api/v1/integration-providers', [
            'kind' => 'payroll',
            'endpoint' => 'https://payroll.example.test/push',
            'secret' => 'payroll-secret',
            'enabled' => true,
        ], $this->header($organization))->assertCreated();
        $employee = Employee::factory()->create(['organization_id' => $organization->id, 'employee_code' => 'OWN-900']);
        $this->postJson('/api/v1/integrations/biometric/punches', [
            'employee_id' => $foreign->id,
            'work_date' => '2026-11-17',
            'punched_at' => '2026-11-17 09:00:00',
            'kind' => 'in',
            'work_mode' => 'office',
        ], [...$this->header($organization), 'Authorization' => 'Bearer device-secret'])->assertNotFound();
        $this->postJson('/api/v1/integrations/biometric/punches', [
            'employee_id' => $employee->id,
            'work_date' => '2026-11-17',
            'punched_at' => '2026-11-17 09:00:00',
            'kind' => 'in',
            'work_mode' => 'office',
        ], [...$this->header($organization), 'Authorization' => 'Bearer wrong'])->assertUnauthorized();
        $this->postJson('/api/v1/integrations/biometric/punches', [
            'employee_id' => $employee->id,
            'work_date' => '2026-11-17',
            'punched_at' => '2026-11-17 09:00:00',
            'kind' => 'in',
            'work_mode' => 'office',
        ], [...$this->header($organization), 'Authorization' => 'Bearer device-secret'])->assertCreated();

        Http::fake(['https://payroll.example.test/*' => Http::response(['accepted' => true], 202)]);
        $run = PayrollRun::unguarded(fn () => PayrollRun::query()->create([
            'organization_id' => $organization->id, 'legal_entity_id' => $entity->id, 'year' => 2026, 'month' => 11, 'status' => 'locked', 'warnings' => [],
        ]));
        app(IntegrationDispatcher::class)->payroll($organization, $run);
        $this->assertSame('sent', IntegrationDelivery::withoutGlobalScopes()->where('subject', 'run:'.$run->id)->value('status'));
        Http::assertSent(fn ($request) => $request->url() === 'https://payroll.example.test/push' && ! str_contains($request->body(), 'FOREIGN-900'));

        $workflow = $this->postJson('/api/v1/workflows', [
            'name' => 'Leave notice',
            'event' => 'leave.applied',
            'steps' => [['permission' => 'leave.approve']],
            'enabled' => true,
        ], $this->header($organization))->assertCreated();
        $this->postJson('/api/v1/workflows/'.$workflow->json('data.id').'/run', ['subject' => 'manual-1'], $this->header($organization))->assertOk();
        $this->assertSame(1, AppNotification::withoutGlobalScopes()->where('user_id', $admin->id)->where('event', 'workflow.fired')->count());
        $this->assertSame(0, AppNotification::withoutGlobalScopes()->where('user_id', $outsider->id)->count());
        $this->getJson('/api/v1/attrition', $this->header($organization))->assertOk()->assertJsonPath('data.exits', 0);
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
