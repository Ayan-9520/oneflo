<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CompanyProfile;
use App\Models\Employee;
use App\Models\IssuedDocument;
use App\Models\LegalEntity;
use App\Models\Organization;
use App\Models\OrganizationAccess;
use App\Models\PayrollLine;
use App\Models\PayrollRun;
use App\Models\Role;
use App\Models\User;
use App\Services\OrganizationContext;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_named_tenant_boundaries_and_production_controls_hold(): void
    {
        [$motorcart, $motorHr] = $this->member('hr-admin');
        $motorcart->update(['name' => 'Motorcart']);
        [$rupee, $rupeeHr] = $this->member('hr-admin');
        $rupee->update(['name' => 'RupeeDial']);
        $motorEmployee = Employee::factory()->create([
            'organization_id' => $motorcart->id,
            'employee_code' => 'EMP-MC-PRIVATE',
            'given_name' => 'Private',
            'family_name' => 'Motorcart',
        ]);
        Employee::factory()->create([
            'organization_id' => $rupee->id,
            'employee_code' => 'EMP-RD-PRIVATE',
        ]);

        $this->actingAs($rupeeHr);
        $this->getJson('/api/v1/employees/'.$motorEmployee->id, $this->header($rupee))->assertNotFound();
        $this->getJson('/api/v1/employees', $this->header($rupee))
            ->assertOk()
            ->assertJsonMissing(['employee_code' => 'EMP-MC-PRIVATE']);
        $this->getJson('/api/v1/employees', $this->header($motorcart))->assertForbidden();

        [$motorPayrollOrg, $motorManager] = $this->member('department-manager');
        $foreignEntity = LegalEntity::unguarded(fn () => LegalEntity::query()->create([
            'organization_id' => $rupee->id,
            'legal_name' => 'RupeeDial Legal',
        ]));
        $foreignRun = PayrollRun::unguarded(fn () => PayrollRun::query()->create([
            'organization_id' => $rupee->id,
            'legal_entity_id' => $foreignEntity->id,
            'year' => 2026,
            'month' => 8,
            'status' => 'locked',
            'open_period' => '2026-08',
            'warnings' => [],
        ]));
        $this->actingAs($motorManager);
        $this->getJson('/api/v1/payroll/runs', $this->header($rupee))->assertForbidden();
        $this->getJson('/api/v1/payroll/runs/'.$foreignRun->id, $this->header($motorPayrollOrg))->assertNotFound();

        app(OrganizationContext::class)->forget();
        $staffUser = User::factory()->create();
        $otherUser = User::factory()->create();
        OrganizationAccess::query()->create([
            'organization_id' => $motorcart->id,
            'user_id' => $staffUser->id,
            'role_id' => Role::query()->where('slug', 'employee')->value('id'),
        ]);
        OrganizationAccess::query()->create([
            'organization_id' => $motorcart->id,
            'user_id' => $otherUser->id,
            'role_id' => Role::query()->where('slug', 'employee')->value('id'),
        ]);
        $staff = Employee::factory()->create([
            'organization_id' => $motorcart->id,
            'user_id' => $staffUser->id,
            'employee_code' => 'EMP-MC-STAFF',
        ]);
        $colleague = Employee::factory()->create([
            'organization_id' => $motorcart->id,
            'user_id' => $otherUser->id,
            'employee_code' => 'EMP-MC-OTHER',
        ]);
        $motorEntity = LegalEntity::unguarded(fn () => LegalEntity::query()->create([
            'organization_id' => $motorcart->id,
            'legal_name' => 'Motorcart Legal',
        ]));
        $run = PayrollRun::unguarded(fn () => PayrollRun::query()->create([
            'organization_id' => $motorcart->id,
            'legal_entity_id' => $motorEntity->id,
            'year' => 2026,
            'month' => 9,
            'status' => 'locked',
            'open_period' => '2026-09',
            'warnings' => [],
        ]));
        $ownLine = PayrollLine::unguarded(fn () => PayrollLine::query()->create([
            'organization_id' => $motorcart->id, 'payroll_run_id' => $run->id, 'employee_id' => $staff->id,
            'earnings' => [], 'deductions' => [], 'employer' => [], 'gross_paise' => 100, 'deduction_paise' => 0, 'net_paise' => 100,
        ]));
        $otherLine = PayrollLine::unguarded(fn () => PayrollLine::query()->create([
            'organization_id' => $motorcart->id, 'payroll_run_id' => $run->id, 'employee_id' => $colleague->id,
            'earnings' => [], 'deductions' => [], 'employer' => [], 'gross_paise' => 200, 'deduction_paise' => 0, 'net_paise' => 200,
        ]));
        $this->actingAs($staffUser);
        $this->get('/api/v1/payroll/lines/'.$otherLine->id.'/payslip', $this->header($motorcart))->assertForbidden();
        $this->get('/api/v1/payroll/lines/'.$ownLine->id.'/payslip', $this->header($motorcart))->assertOk();

        IssuedDocument::unguarded(fn () => IssuedDocument::query()->create([
            'organization_id' => $motorcart->id,
            'legal_entity_id' => $motorEntity->id,
            'employee_id' => $colleague->id,
            'document_type' => 'general_letter',
            'number' => 'MC-LETTER',
            'version' => 1,
            'issued_on' => '2026-11-18',
            'html' => 'Motorcart letter',
            'lines' => ['Motorcart letter'],
            'oneflo_attribution' => false,
        ]));
        $documentId = IssuedDocument::query()->where('number', 'MC-LETTER')->value('id');
        $this->get('/api/v1/issued-documents/'.$documentId.'/pdf', $this->header($motorcart))->assertForbidden();

        CompanyProfile::unguarded(fn () => CompanyProfile::query()->create([
            'organization_id' => $motorcart->id,
            'legal_entity_id' => $motorEntity->id,
            'legal_company_name' => 'Motorcart Private Limited',
            'brand_name' => 'Motorcart',
        ]));
        $this->actingAs($motorHr);
        $preview = $this->postJson('/api/v1/document-previews', [
            'legal_entity_id' => $motorEntity->id,
            'document_type' => 'general_letter',
            'name' => 'Letter',
            'body' => 'Issued for {{company.legal_name}}. <script>alert(1)</script>',
            'show_logo' => false,
            'show_signatory' => false,
        ], $this->header($motorcart))->assertOk();
        $this->assertStringContainsString('Motorcart Private Limited', $preview->json('data.html'));
        $this->assertStringNotContainsString('RupeeDial Legal', $preview->json('data.html'));
        $this->assertStringNotContainsString('<script>', $preview->json('data.html'));
        $this->assertStringContainsString('&lt;script&gt;', $preview->json('data.html'));
        $this->postJson('/api/v1/document-previews', [
            'legal_entity_id' => $foreignEntity->id,
            'document_type' => 'general_letter',
            'name' => 'Letter',
            'body' => 'Issued for {{company.legal_name}}.',
            'show_logo' => false,
            'show_signatory' => false,
        ], $this->header($motorcart))->assertNotFound();

        [$payrollOrg, $payrollAdmin] = $this->member('payroll-admin');
        $payrollEntity = LegalEntity::unguarded(fn () => LegalEntity::query()->create([
            'organization_id' => $payrollOrg->id,
            'legal_name' => 'Payroll Employer',
        ]));
        $this->actingAs($payrollAdmin);
        $this->postJson('/api/v1/payroll/runs', [
            'legal_entity_id' => $payrollEntity->id,
            'year' => 2026,
            'month' => 10,
        ], $this->header($payrollOrg))->assertCreated();
        $this->postJson('/api/v1/payroll/runs', [
            'legal_entity_id' => $payrollEntity->id,
            'year' => 2026,
            'month' => 10,
        ], $this->header($payrollOrg))->assertUnprocessable();

        app(OrganizationContext::class)->forget();
        $manager = User::factory()->create();
        OrganizationAccess::query()->create([
            'organization_id' => $motorcart->id,
            'user_id' => $manager->id,
            'role_id' => Role::query()->where('slug', 'department-manager')->value('id'),
        ]);
        $this->actingAs($manager);
        $this->getJson('/api/v1/reports/headcount', $this->header($motorcart))->assertOk();
        $this->get('/api/v1/reports/headcount/export?format=csv', $this->header($motorcart))->assertForbidden();

        $super = User::factory()->create();
        $super->platformRoles()->attach(Role::query()->where('slug', 'super-admin')->value('id'));
        $this->actingAs($super);
        $this->getJson('/api/v1/organizations')->assertOk()
            ->assertJsonFragment(['name' => 'Motorcart'])
            ->assertJsonFragment(['name' => 'RupeeDial']);

        app(OrganizationContext::class)->forget();
        $mover = User::factory()->create();
        OrganizationAccess::query()->create([
            'organization_id' => $motorcart->id,
            'user_id' => $mover->id,
            'role_id' => Role::query()->where('slug', 'business-admin')->value('id'),
        ]);
        OrganizationAccess::query()->create([
            'organization_id' => $rupee->id,
            'user_id' => $mover->id,
            'role_id' => Role::query()->where('slug', 'business-admin')->value('id'),
        ]);
        $this->actingAs($mover);
        $transferred = $this->postJson('/api/v1/employees/'.$motorEmployee->id.'/transfer', [
            'target_organization_id' => $rupee->id,
            'legal_entity_id' => $foreignEntity->id,
            'employee_code' => 'EMP-RD-TRANSFER',
            'effective_on' => '2026-11-18',
        ], $this->header($motorcart))->assertCreated();
        $this->assertSame('transferred', $motorEmployee->refresh()->status);
        $this->assertDatabaseHas('employment_records', [
            'employee_id' => $motorEmployee->id,
            'change_type' => 'transfer',
        ]);
        $this->assertDatabaseHas('employees', [
            'id' => $transferred->json('data.employee_id'),
            'organization_id' => $rupee->id,
            'employee_code' => 'EMP-RD-TRANSFER',
        ]);

        $log = AuditLog::query()->create([
            'actor_user_id' => $super->id,
            'organization_id' => $motorcart->id,
            'action' => 'audit.probe',
            'metadata' => [],
            'created_at' => now(),
        ]);
        $this->expectException(\LogicException::class);
        $log->action = 'audit.changed';
        $log->save();
    }

    public function test_login_attempts_are_rate_limited_and_memory_backups_are_refused(): void
    {
        $user = User::factory()->create();
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => $user->email,
                'password' => 'wrong-password',
            ])->assertUnprocessable();
        }
        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertStatus(429);

        $this->artisan('backup:database')->assertFailed();
    }

    /**
     * @return array{0: Organization, 1: User}
     */
    private function member(string $roleSlug): array
    {
        app(OrganizationContext::class)->forget();
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
