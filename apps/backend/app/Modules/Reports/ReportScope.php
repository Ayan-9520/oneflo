<?php

declare(strict_types=1);

namespace App\Modules\Reports;

final class ReportScope
{
    public function __construct(
        public readonly ?string $from,
        public readonly ?string $to,
        public readonly ?int $legalEntityId,
        public readonly ?int $departmentId,
        public readonly ?int $branchId,
        public readonly ?int $designationId,
        public readonly ?int $employeeId,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public static function fromArray(array $filters): self
    {
        return new self(
            isset($filters['from']) ? (string) $filters['from'] : null,
            isset($filters['to']) ? (string) $filters['to'] : null,
            isset($filters['legal_entity_id']) ? (int) $filters['legal_entity_id'] : null,
            isset($filters['department_id']) ? (int) $filters['department_id'] : null,
            isset($filters['branch_id']) ? (int) $filters['branch_id'] : null,
            isset($filters['designation_id']) ? (int) $filters['designation_id'] : null,
            isset($filters['employee_id']) ? (int) $filters['employee_id'] : null,
        );
    }
}
