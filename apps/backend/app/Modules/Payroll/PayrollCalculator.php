<?php

declare(strict_types=1);

namespace App\Modules\Payroll;

/**
 * Applies only the rule values it is given. It does not contain statutory rates.
 */
final class PayrollCalculator
{
    /**
     * @param  array<string, int>  $monthlyPaise
     * @param  array<string, string>  $kinds
     * @param  list<string>  $taxable
     * @param  array<string, mixed>|null  $epf
     * @param  array<string, mixed>|null  $esi
     * @param  array<string, mixed>|null  $professionalTax
     * @param  array<string, mixed>|null  $tds
     * @param  array<string, int>  $adjustmentsPaise
     * @return array{earnings: array<string, int>, deductions: array<string, int>, employer: array<string, int>, gross: int, deduction_total: int, net: int, warnings: list<string>}
     */
    public static function month(
        array $monthlyPaise,
        array $kinds,
        array $taxable,
        int $calendarDays,
        int $lopDays,
        ?array $epf,
        ?array $esi,
        ?array $professionalTax,
        ?array $tds,
        array $adjustmentsPaise = [],
    ): array {
        $payableDays = max(0, $calendarDays - $lopDays);
        $earnings = [];
        $deductions = [];
        $employer = [];
        $warnings = [];
        if ($calendarDays < 1) {
            $warnings[] = 'The payroll month has no calendar days.';
            $calendarDays = 1;
        }
        foreach ($monthlyPaise as $code => $amount) {
            $prorated = (int) round($amount * $payableDays / $calendarDays);
            $prorated += $adjustmentsPaise[$code] ?? 0;
            $kind = $kinds[$code] ?? 'earning';
            if ($kind === 'deduction') {
                $deductions[$code] = $prorated;
            } elseif ($kind === 'employer_contribution') {
                $employer[$code] = $prorated;
            } else {
                $earnings[$code] = $prorated;
            }
        }

        $epfWage = self::wage($earnings, $epf['wage_components'] ?? []);
        if ($epf === null) {
            $warnings[] = 'EPF was not calculated because no confirmed rule is effective for this month.';
        } else {
            $ceiling = self::rupees($epf['wage_ceiling'] ?? null);
            if ($ceiling !== null) {
                $epfWage = min($epfWage, $ceiling);
            }
            $deductions['epf_employee'] = self::percent($epfWage, (float) $epf['employee_percent']);
            $employer['epf_employer'] = self::percent($epfWage, (float) $epf['employer_percent']);
        }

        $esiWage = self::wage($earnings, $esi['wage_components'] ?? []);
        if ($esi === null) {
            $warnings[] = 'ESI was not calculated because no confirmed rule is effective for this month.';
        } else {
            $ceiling = self::rupees($esi['wage_ceiling'] ?? null);
            if ($ceiling !== null && $esiWage > $ceiling) {
                $deductions['esi_employee'] = 0;
                $employer['esi_employer'] = 0;
            } else {
                $deductions['esi_employee'] = self::percent($esiWage, (float) $esi['employee_percent']);
                $employer['esi_employer'] = self::percent($esiWage, (float) $esi['employer_percent']);
            }
        }

        if ($professionalTax === null) {
            $warnings[] = 'Professional tax was not calculated because no confirmed rule is effective for this month.';
        } else {
            $deductions['professional_tax'] = self::slabAmount(self::wage($earnings, $professionalTax['wage_components'] ?? array_keys($earnings)), $professionalTax['slabs'] ?? []);
        }

        if ($tds === null) {
            $warnings[] = 'TDS was not calculated because no confirmed rule is effective for this month.';
        } else {
            $annual = 0;
            foreach ($earnings as $code => $amount) {
                if (in_array($code, $taxable, true)) {
                    $annual += $amount * 12;
                }
            }
            $annual -= ($deductions['epf_employee'] ?? 0) * 12;
            $annual -= self::rupees($tds['standard_deduction'] ?? 0) ?? 0;
            $annual = max(0, $annual);
            $tax = self::slabTax($annual, $tds['slabs'] ?? []);
            $rebateLimit = self::rupees($tds['rebate_income_limit'] ?? null);
            $rebate = self::rupees($tds['rebate_amount'] ?? 0) ?? 0;
            if ($rebateLimit !== null && $annual <= $rebateLimit) {
                $tax = max(0, $tax - $rebate);
            }
            $cess = (int) round($tax * (float) ($tds['cess_percent'] ?? 0) / 100);
            $deductions['tds'] = (int) round(($tax + $cess) / 12);
            $warnings[] = 'TDS is a monthly projection from the configured slabs, not a completed assessment.';
        }

        $gross = array_sum($earnings);
        $deductionTotal = array_sum($deductions);

        return [
            'earnings' => $earnings,
            'deductions' => $deductions,
            'employer' => $employer,
            'gross' => $gross,
            'deduction_total' => $deductionTotal,
            'net' => $gross - $deductionTotal,
            'warnings' => $warnings,
        ];
    }

    /**
     * @param  array<string, mixed>  $rule
     */
    public static function gratuity(int $lastDrawnMonthlyPaise, int $completedYears, array $rule): int
    {
        $minimum = (int) ($rule['minimum_years'] ?? 0);
        if ($completedYears < $minimum) {
            return 0;
        }
        $divisor = (int) ($rule['divisor'] ?? 1);
        if ($divisor < 1) {
            return 0;
        }

        return (int) round($lastDrawnMonthlyPaise / $divisor * (float) $rule['days_per_year'] * $completedYears);
    }

    public static function leaveEncashment(int $monthlyBasicPaise, float $days, int $dayDivisor): int
    {
        if ($dayDivisor < 1) {
            return 0;
        }

        return (int) round($monthlyBasicPaise / $dayDivisor * $days);
    }

    /**
     * @param  array<string, int>  $earnings
     * @param  list<string>  $components
     */
    private static function wage(array $earnings, array $components): int
    {
        $total = 0;
        foreach ($components as $code) {
            $total += $earnings[$code] ?? 0;
        }

        return $total;
    }

    private static function percent(int $paise, float $percent): int
    {
        return (int) round($paise * $percent / 100);
    }

    private static function rupees(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) round(((float) $value) * 100);
    }

    /**
     * @param  list<array{upto: float|int|null, amount?: float|int, rate?: float|int}>  $slabs
     */
    private static function slabAmount(int $wagePaise, array $slabs): int
    {
        $wage = $wagePaise / 100;
        $matched = 0.0;
        foreach ($slabs as $slab) {
            $upto = $slab['upto'];
            if ($upto === null || $wage <= (float) $upto) {
                $matched = (float) ($slab['amount'] ?? 0);
                break;
            }
        }

        return (int) round($matched * 100);
    }

    /**
     * @param  list<array{upto: float|int|null, rate: float|int}>  $slabs
     */
    private static function slabTax(int $incomePaise, array $slabs): int
    {
        $ordered = $slabs;
        usort($ordered, function (array $left, array $right): int {
            if ($left['upto'] === null) {
                return 1;
            }
            if ($right['upto'] === null) {
                return -1;
            }

            return $left['upto'] <=> $right['upto'];
        });
        $tax = 0;
        $previous = 0;
        foreach ($ordered as $slab) {
            $upto = $slab['upto'] === null ? $incomePaise : min($incomePaise, (int) round(((float) $slab['upto']) * 100));
            $slice = max(0, min($incomePaise, $upto) - $previous);
            $tax += (int) round($slice * (float) $slab['rate'] / 100);
            $previous = $upto;
            if ($incomePaise <= $previous) {
                break;
            }
        }

        return $tax;
    }
}
