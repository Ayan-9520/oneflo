<?php

declare(strict_types=1);

namespace App\Modules\Attendance;

use DateTimeImmutable;

final class AttendanceCalculator
{
    /**
     * @param  list<array{kind: string, at: string}>  $punches
     * @return array{status: string, worked_minutes: int, break_minutes: int, scheduled_minutes: int, late_minutes: int, early_minutes: int, overtime_minutes: int}
     */
    public static function evaluate(
        string $workDate,
        ?string $startsAt,
        ?string $endsAt,
        int $graceMinutes,
        int $earlyGraceMinutes,
        int $overtimeThresholdMinutes,
        bool $weeklyOff,
        bool $holiday,
        bool $onLeave,
        array $punches,
    ): array {
        $scheduled = 0;
        $start = null;
        $end = null;
        if ($startsAt !== null && $endsAt !== null && ! $weeklyOff && ! $holiday && ! $onLeave) {
            $start = new DateTimeImmutable($workDate.' '.$startsAt);
            $end = new DateTimeImmutable($workDate.' '.$endsAt);
            if ($end <= $start) {
                $end = $end->modify('+1 day');
            }
            $scheduled = intdiv($end->getTimestamp() - $start->getTimestamp(), 60);
        }

        $ordered = $punches;
        usort($ordered, fn (array $left, array $right) => strcmp($left['at'], $right['at']));
        $worked = 0;
        $breaks = 0;
        $open = null;
        $paused = null;
        $missing = false;
        $firstIn = null;
        $lastOut = null;
        foreach ($ordered as $punch) {
            $at = new DateTimeImmutable($punch['at']);
            if ($punch['kind'] === 'in') {
                if ($open !== null) {
                    $missing = true;
                }
                $open = $at;
                $firstIn ??= $at;
            } elseif ($punch['kind'] === 'break_start') {
                if ($open === null || $paused !== null) {
                    $missing = true;
                } else {
                    $worked += max(0, intdiv($at->getTimestamp() - $open->getTimestamp(), 60));
                    $paused = $at;
                    $open = null;
                }
            } elseif ($punch['kind'] === 'break_end') {
                if ($paused === null) {
                    $missing = true;
                } else {
                    $breaks += max(0, intdiv($at->getTimestamp() - $paused->getTimestamp(), 60));
                    $open = $at;
                    $paused = null;
                }
            } elseif ($punch['kind'] === 'out') {
                if ($open === null) {
                    $missing = true;
                } else {
                    $worked += max(0, intdiv($at->getTimestamp() - $open->getTimestamp(), 60));
                    $open = null;
                    $lastOut = $at;
                }
            }
        }
        if ($open !== null || $paused !== null) {
            $missing = true;
        }

        $late = 0;
        $early = 0;
        if ($start !== null && $firstIn !== null) {
            $afterStart = intdiv($firstIn->getTimestamp() - $start->getTimestamp(), 60);
            if ($afterStart > $graceMinutes) {
                $late = $afterStart;
            }
        }
        if ($end !== null && $lastOut !== null) {
            $beforeEnd = intdiv($end->getTimestamp() - $lastOut->getTimestamp(), 60);
            if ($beforeEnd > $earlyGraceMinutes) {
                $early = $beforeEnd;
            }
        }

        $overtime = 0;
        if (! $missing) {
            $overtime = max(0, $worked - $scheduled - $overtimeThresholdMinutes);
        }

        $status = 'absent';
        if ($missing && $ordered !== []) {
            $status = 'missing_punch';
        } elseif ($worked === 0 && $onLeave) {
            $status = 'leave';
        } elseif ($worked === 0 && $holiday) {
            $status = 'holiday';
        } elseif ($worked === 0 && $weeklyOff) {
            $status = 'weekly_off';
        } elseif ($worked === 0) {
            $status = 'absent';
        } elseif ($scheduled > 0 && $worked < intdiv($scheduled, 2)) {
            $status = 'half_day';
        } else {
            $status = 'present';
        }

        return [
            'status' => $status,
            'worked_minutes' => $worked,
            'break_minutes' => $breaks,
            'scheduled_minutes' => $scheduled,
            'late_minutes' => $late,
            'early_minutes' => $early,
            'overtime_minutes' => $overtime,
        ];
    }
}
