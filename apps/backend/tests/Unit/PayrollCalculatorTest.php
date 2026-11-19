<?php

namespace Tests\Unit;

use App\Modules\Payroll\PayrollCalculator;
use PHPUnit\Framework\TestCase;

class PayrollCalculatorTest extends TestCase
{
    public function test_it_prorates_pay_and_applies_only_the_supplied_rules(): void
    {
        $full = PayrollCalculator::month(
            ['basic' => 3_000_000, 'hra' => 1_000_000],
            ['basic' => 'earning', 'hra' => 'earning'],
            ['basic', 'hra'],
            30,
            0,
            ['employee_percent' => 12, 'employer_percent' => 12, 'wage_ceiling' => 15000, 'wage_components' => ['basic']],
            ['employee_percent' => 0.75, 'employer_percent' => 3.25, 'wage_ceiling' => 21000, 'wage_components' => ['basic', 'hra']],
            ['wage_components' => ['basic', 'hra'], 'slabs' => [['upto' => 15000, 'amount' => 0], ['upto' => null, 'amount' => 200]]],
            null,
        );

        $this->assertSame(3_000_000, $full['earnings']['basic']);
        $this->assertSame(180_000, $full['deductions']['epf_employee']);
        $this->assertSame(180_000, $full['employer']['epf_employer']);
        $this->assertSame(0, $full['deductions']['esi_employee']);
        $this->assertSame(20_000, $full['deductions']['professional_tax']);
        $this->assertSame(3_800_000, $full['net']);

        $prorated = PayrollCalculator::month(
            ['basic' => 3_000_000],
            ['basic' => 'earning'],
            ['basic'],
            30,
            2,
            null,
            null,
            null,
            null,
        );
        $this->assertSame(2_800_000, $prorated['earnings']['basic']);
        $this->assertNotContains('EPF was not calculated because no confirmed rule is effective for this month.', []);
        $this->assertTrue(collect($prorated['warnings'])->contains(fn (string $warning) => str_contains($warning, 'EPF')));
    }

    public function test_tds_uses_the_configured_slabs_and_gratuity_respects_the_minimum_service(): void
    {
        $result = PayrollCalculator::month(
            ['basic' => 5_000_000],
            ['basic' => 'earning'],
            ['basic'],
            30,
            0,
            null,
            null,
            null,
            [
                'standard_deduction' => 75000,
                'slabs' => [
                    ['upto' => 400000, 'rate' => 0],
                    ['upto' => 800000, 'rate' => 5],
                ],
                'rebate_income_limit' => 0,
                'rebate_amount' => 0,
                'cess_percent' => 4,
            ],
        );
        $this->assertArrayHasKey('tds', $result['deductions']);
        $this->assertGreaterThan(0, $result['deductions']['tds']);

        $this->assertSame(0, PayrollCalculator::gratuity(2_600_000, 4, ['minimum_years' => 5, 'divisor' => 26, 'days_per_year' => 15]));
        $this->assertSame(15_000_000, PayrollCalculator::gratuity(2_600_000, 10, ['minimum_years' => 5, 'divisor' => 26, 'days_per_year' => 15]));
        $this->assertSame(100_000, PayrollCalculator::leaveEncashment(3_000_000, 1, 30));
    }
}
