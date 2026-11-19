<?php

declare(strict_types=1);

namespace App\Modules\Notifications;

final class NotificationEvents
{
    public const LEAVE_APPLIED = 'leave.applied';

    public const LEAVE_APPROVED = 'leave.approved';

    public const ATTENDANCE_EXCEPTION = 'attendance.exception';

    public const PAYROLL_PUBLISHED = 'payroll.published';

    public const PAYSLIP_AVAILABLE = 'payslip.available';

    public const EMPLOYEE_JOINED = 'employee.joined';

    public const DOCUMENT_PENDING = 'document.pending';

    public const ASSET_RETURNED = 'asset.returned';

    public const PERFORMANCE_REVIEW = 'performance.review';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::LEAVE_APPLIED,
            self::LEAVE_APPROVED,
            self::ATTENDANCE_EXCEPTION,
            self::PAYROLL_PUBLISHED,
            self::PAYSLIP_AVAILABLE,
            self::EMPLOYEE_JOINED,
            self::DOCUMENT_PENDING,
            self::ASSET_RETURNED,
            self::PERFORMANCE_REVIEW,
        ];
    }
}
