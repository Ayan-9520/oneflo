<?php

namespace Tests\Feature;

use App\Models\AttendanceDay;
use App\Models\CompanyProfile;
use App\Models\Employee;
use App\Models\LegalEntity;
use App\Models\Organization;
use App\Models\OrganizationAccess;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_a_preview_uses_confirmed_rules_and_a_locked_run_cannot_be_edited_or_duplicated(): void
    {
        [$organization, $processor] = $this->member('payroll-admin');
        $approver = User::factory()->create();
        OrganizationAccess::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $approver->id,
            'role_id' => Role::query()->where('slug', 'payroll-admin')->value('id'),
        ]);
        $entity = LegalEntity::unguarded(fn () => LegalEntity::query()->create([
            'organization_id' => $organization->id,
            'legal_name' => 'Northwind Engineering',
        ]));
        CompanyProfile::unguarded(fn () => CompanyProfile::query()->create([
            'organization_id' => $organization->id,
            'legal_entity_id' => $entity->id,
            'legal_company_name' => 'Northwind Engineering',
            'brand_name' => 'Northwind',
            'pan' => 'ABCDE1234F',
            'tan' => 'BLRN01234E',
        ]));
        $employee = Employee::factory()->create([
            'organization_id' => $organization->id,
            'legal_entity_id' => $entity->id,
            'status' => 'active',
        ]);
        for ($day = 1; $day <= 30; $day++) {
            AttendanceDay::unguarded(fn () => AttendanceDay::query()->create([
                'organization_id' => $organization->id,
                'employee_id' => $employee->id,
                'work_date' => sprintf('2026-11-%02d', $day),
                'status' => 'present',
                'worked_minutes' => 480,
            ]));
        }
        $this->actingAs($processor);
        $basic = $this->postJson('/api/v1/payroll/components', [
            'code' => 'basic', 'name' => 'Basic salary', 'kind' => 'earning', 'taxable' => true,
        ], $this->header($organization))->assertCreated()->json('data.id');
        $hra = $this->postJson('/api/v1/payroll/components', [
            'code' => 'hra', 'name' => 'HRA', 'kind' => 'earning', 'taxable' => true,
        ], $this->header($organization))->assertCreated()->json('data.id');
        $structure = $this->postJson('/api/v1/payroll/structures', [
            'legal_entity_id' => $entity->id,
            'name' => 'Standard',
            'effective_on' => '2026-04-01',
            'lines' => [
                ['payroll_component_id' => $basic, 'monthly_amount' => 30000],
                ['payroll_component_id' => $hra, 'monthly_amount' => 10000],
            ],
        ], $this->header($organization))->assertCreated()->json('data.id');
        $this->postJson('/api/v1/payroll/compensations', [
            'employee_id' => $employee->id,
            'salary_structure_id' => $structure,
            'tax_regime' => 'new',
        ], $this->header($organization))->assertOk();
        foreach ([
            ['epf', ['employee_percent' => 12, 'employer_percent' => 12, 'wage_ceiling' => 15000, 'wage_components' => ['basic']]],
            ['esi', ['employee_percent' => 0.75, 'employer_percent' => 3.25, 'wage_ceiling' => 21000, 'wage_components' => ['basic', 'hra']]],
            ['professional_tax', ['wage_components' => ['basic', 'hra'], 'slabs' => [['upto' => 15000, 'amount' => 0], ['upto' => null, 'amount' => 200]]]],
            ['tds', ['standard_deduction' => 0, 'slabs' => [['upto' => null, 'rate' => 0]], 'cess_percent' => 0]],
        ] as [$code, $config]) {
            $rule = $this->postJson('/api/v1/statutory-rules', [
                'legal_entity_id' => $entity->id,
                'code' => $code,
                'effective_on' => '2026-04-01',
                'config' => $config,
                'citation' => 'Confirmed by the legal employer for this scenario',
            ], $this->header($organization))->assertCreated()->json('data.id');
            $this->postJson('/api/v1/statutory-rules/'.$rule.'/confirm', [], $this->header($organization))->assertOk();
        }

        $run = $this->postJson('/api/v1/payroll/runs', [
            'legal_entity_id' => $entity->id, 'year' => 2026, 'month' => 11,
        ], $this->header($organization))->assertCreated()->json('data.id');
        $this->postJson('/api/v1/payroll/runs', [
            'legal_entity_id' => $entity->id, 'year' => 2026, 'month' => 11,
        ], $this->header($organization))->assertUnprocessable();

        $preview = $this->postJson('/api/v1/payroll/runs/'.$run.'/preview', [], $this->header($organization))->assertOk();
        $preview->assertJsonPath('data.lines.0.net', '38000.00');
        $this->postJson('/api/v1/payroll/runs/'.$run.'/submit', [], $this->header($organization))->assertOk();
        $this->postJson('/api/v1/payroll/runs/'.$run.'/approve', [], $this->header($organization))->assertUnprocessable();
        $this->actingAs($approver);
        $this->postJson('/api/v1/payroll/runs/'.$run.'/approve', [], $this->header($organization))->assertOk();
        $this->postJson('/api/v1/payroll/runs/'.$run.'/lock', [], $this->header($organization))->assertOk();
        $this->postJson('/api/v1/payroll/runs/'.$run.'/adjustments', [
            'employee_id' => $employee->id, 'component_code' => 'basic', 'amount' => 100, 'reason' => 'Too late',
        ], $this->header($organization))->assertUnprocessable();

        $form = $this->getJson('/api/v1/payroll/form-16?legal_entity_id='.$entity->id.'&financial_year=2026', $this->header($organization))->assertOk();
        $form->assertJsonPath('data.deductor.legal_name', 'Northwind Engineering');
        $this->assertStringContainsString('not a Form 16', $form->json('data.disclaimer'));
        $this->assertStringContainsString('XXXX', (string) $form->json('data.deductor.pan'));
        $this->assertStringNotContainsString('ABCDE1234F', json_encode($form->json()));

        $this->postJson('/api/v1/payroll/runs/'.$run.'/reverse', ['reason' => 'Input corrected'], $this->header($organization))->assertOk();
        $this->actingAs($processor);
        $this->postJson('/api/v1/payroll/runs', [
            'legal_entity_id' => $entity->id, 'year' => 2026, 'month' => 11,
        ], $this->header($organization))->assertCreated();
    }

    public function test_another_business_cannot_open_payroll_for_a_foreign_legal_entity(): void
    {
        [$organization, $user] = $this->member('payroll-admin');
        $other = Organization::factory()->create();
        $foreign = LegalEntity::unguarded(fn () => LegalEntity::query()->create([
            'organization_id' => $other->id,
            'legal_name' => 'Other Employer',
        ]));
        $this->actingAs($user);
        $this->postJson('/api/v1/payroll/runs', [
            'legal_entity_id' => $foreign->id, 'year' => 2026, 'month' => 11,
        ], $this->header($organization))->assertNotFound();
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
