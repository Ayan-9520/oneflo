<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Validation\ValidationException;

final class DocumentPlaceholders
{
    /**
     * @return array<string, string>
     */
    public static function descriptions(): array
    {
        return [
            'company.legal_name' => 'Legal company name',
            'company.brand_name' => 'Brand name',
            'company.address' => 'Registered address',
            'company.cin' => 'CIN',
            'company.pan' => 'PAN',
            'company.tan' => 'TAN',
            'company.gstin' => 'GSTIN',
            'company.email' => 'Contact email',
            'company.phone' => 'Contact phone',
            'company.website' => 'Website',
            'employee.full_name' => 'Employee full name',
            'employee.given_name' => 'Employee given name',
            'employee.family_name' => 'Employee family name',
            'employee.employee_code' => 'Employee code',
            'employee.designation' => 'Designation recorded on the employee',
            'employee.position_phrase' => 'Position phrase, or a note when designation is not saved',
            'employee.department' => 'Department recorded on the employee',
            'employee.joining_date' => 'Joining date recorded on the employee',
            'employee.work_location' => 'Work location recorded on the employee',
            'employee.email' => 'Employee email',
            'employee.phone' => 'Employee phone',
            'employee.manager' => 'Reporting manager recorded on the employee',
            'employee.annual_fixed' => 'Annual fixed pay from the saved salary structure',
            'employee.annual_variable' => 'Annual variable pay from the saved salary structure',
            'employee.annual_ctc' => 'Annual cost to company from fixed and variable pay',
            'employee.compensation_paragraph' => 'Compensation paragraph from the saved salary structure',
            'employee.joining_sentence' => 'Joining-date sentence from the saved joining date',
            'document.date' => 'Document date',
            'document.number' => 'Document number',
            'signatory.name' => 'Signatory name',
            'signatory.designation' => 'Signatory designation',
        ];
    }

    /**
     * @param  array<string, string>  $values
     */
    public static function fill(string $body, array $values): string
    {
        self::assertKnown($body);

        return (string) preg_replace_callback(
            '/\{\{\s*([a-z0-9_.]+)\s*\}\}/',
            fn (array $match): string => $values[$match[1]] ?? '',
            $body,
        );
    }

    public static function assertKnown(string $body): void
    {
        preg_match_all('/\{\{\s*([^}]+?)\s*\}\}/', $body, $matches);
        $unknown = [];

        foreach ($matches[1] as $token) {
            if (! array_key_exists($token, self::descriptions())) {
                $unknown[] = $token;
            }
        }

        if ($unknown !== []) {
            throw ValidationException::withMessages([
                'body' => ['Unknown placeholders: '.implode(', ', array_unique($unknown))],
            ]);
        }
    }
}
