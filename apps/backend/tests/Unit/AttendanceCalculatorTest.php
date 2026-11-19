<?php

namespace Tests\Unit;

use App\Modules\Attendance\AttendanceCalculator;
use PHPUnit\Framework\TestCase;

class AttendanceCalculatorTest extends TestCase
{
    public function test_late_arrival_early_departure_half_day_overtime_and_breaks(): void
    {
        $late = AttendanceCalculator::evaluate('2026-11-17', '09:00:00', '18:00:00', 10, 10, 30, false, false, false, [
            ['kind' => 'in', 'at' => '2026-11-17 09:20:00'],
            ['kind' => 'out', 'at' => '2026-11-17 18:00:00'],
        ]);
        $this->assertSame('present', $late['status']);
        $this->assertSame(20, $late['late_minutes']);
        $this->assertSame(520, $late['worked_minutes']);
        $this->assertSame(0, $late['overtime_minutes']);

        $half = AttendanceCalculator::evaluate('2026-11-17', '09:00:00', '18:00:00', 10, 0, 0, false, false, false, [
            ['kind' => 'in', 'at' => '2026-11-17 09:00:00'],
            ['kind' => 'out', 'at' => '2026-11-17 12:00:00'],
        ]);
        $this->assertSame('half_day', $half['status']);
        $this->assertSame(360, $half['early_minutes']);

        $withBreak = AttendanceCalculator::evaluate('2026-11-17', '09:00:00', '18:00:00', 0, 0, 0, false, false, false, [
            ['kind' => 'in', 'at' => '2026-11-17 09:00:00'],
            ['kind' => 'break_start', 'at' => '2026-11-17 13:00:00'],
            ['kind' => 'break_end', 'at' => '2026-11-17 13:30:00'],
            ['kind' => 'out', 'at' => '2026-11-17 19:00:00'],
        ]);
        $this->assertSame(30, $withBreak['break_minutes']);
        $this->assertSame(570, $withBreak['worked_minutes']);
        $this->assertSame(30, $withBreak['overtime_minutes']);

        $overtime = AttendanceCalculator::evaluate('2026-11-17', '09:00:00', '18:00:00', 0, 0, 15, false, false, false, [
            ['kind' => 'in', 'at' => '2026-11-17 09:00:00'],
            ['kind' => 'out', 'at' => '2026-11-17 19:00:00'],
        ]);
        $this->assertSame(45, $overtime['overtime_minutes']);
    }

    public function test_missing_punch_weekly_off_holiday_and_leave(): void
    {
        $missing = AttendanceCalculator::evaluate('2026-11-17', '09:00:00', '18:00:00', 0, 0, 0, false, false, false, [
            ['kind' => 'in', 'at' => '2026-11-17 09:00:00'],
        ]);
        $this->assertSame('missing_punch', $missing['status']);
        $this->assertSame(0, $missing['overtime_minutes']);

        $off = AttendanceCalculator::evaluate('2026-11-15', '09:00:00', '18:00:00', 0, 0, 0, true, false, false, []);
        $this->assertSame('weekly_off', $off['status']);
        $this->assertSame(0, $off['scheduled_minutes']);

        $holiday = AttendanceCalculator::evaluate('2026-11-17', '09:00:00', '18:00:00', 0, 0, 0, false, true, false, []);
        $this->assertSame('holiday', $holiday['status']);

        $leave = AttendanceCalculator::evaluate('2026-11-17', '09:00:00', '18:00:00', 0, 0, 0, false, false, true, []);
        $this->assertSame('leave', $leave['status']);

        $absent = AttendanceCalculator::evaluate('2026-11-17', '09:00:00', '18:00:00', 0, 0, 0, false, false, false, []);
        $this->assertSame('absent', $absent['status']);
        $this->assertSame(540, $absent['scheduled_minutes']);
    }
}
