<?php

declare(strict_types=1);

namespace App\Modules\Organization\Services;

use App\Models\DocumentSequence;
use App\Models\Employee;
use App\Models\LegalEntity;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class EmployeeCodeGenerator
{
    public function next(LegalEntity $entity): string
    {
        $prefix = strtoupper(trim((string) $entity->employee_code_prefix));
        if ($prefix === '') {
            return $this->legacy();
        }
        $padding = max(3, min(8, (int) ($entity->employee_code_padding ?: 5)));

        return DB::transaction(function () use ($entity, $prefix, $padding): string {
            $series = 'employee-entity-'.$entity->id;
            $sequence = DocumentSequence::query()->where('series', $series)->lockForUpdate()->first();
            if ($sequence === null) {
                $sequence = DocumentSequence::query()->create(['series' => $series, 'last_number' => 0]);
                $sequence = DocumentSequence::query()->whereKey($sequence->id)->lockForUpdate()->first();
            }
            do {
                $sequence->last_number++;
                if ($sequence->last_number > 99999999) {
                    throw ValidationException::withMessages(['employee_code' => ['The employee ID sequence for this employer is exhausted.']]);
                }
                $code = $prefix.'-'.str_pad((string) $sequence->last_number, $padding, '0', STR_PAD_LEFT);
            } while (Employee::query()->where('employee_code', $code)->exists());
            $sequence->save();

            return $code;
        });
    }

    private function legacy(): string
    {
        return DB::transaction(function (): string {
            $sequence = DocumentSequence::query()->where('series', 'employee')->lockForUpdate()->first();
            if ($sequence === null) {
                $sequence = DocumentSequence::query()->create(['series' => 'employee', 'last_number' => 0]);
                $sequence = DocumentSequence::query()->whereKey($sequence->id)->lockForUpdate()->first();
            }
            do {
                $sequence->last_number++;
                $code = 'EMP-'.str_pad((string) $sequence->last_number, 5, '0', STR_PAD_LEFT);
            } while (Employee::query()->where('employee_code', $code)->exists());
            $sequence->save();

            return $code;
        });
    }
}
