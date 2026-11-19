<?php

declare(strict_types=1);

namespace App\Support;

final class IdentityDocuments
{
    /**
     * Built-in employee file categories. Aadhaar stores a file and an optional last-four reference, never the full number.
     *
     * @return array<string, array{name: string, sensitive: bool, requires_retention: bool}>
     */
    public static function builtin(): array
    {
        return [
            'aadhaar' => ['name' => 'Aadhaar document', 'sensitive' => true, 'requires_retention' => true],
            'pan' => ['name' => 'PAN document', 'sensitive' => true, 'requires_retention' => true],
            'address_proof' => ['name' => 'Address proof', 'sensitive' => false, 'requires_retention' => false],
            'education' => ['name' => 'Educational certificate', 'sensitive' => false, 'requires_retention' => false],
            'experience' => ['name' => 'Experience certificate', 'sensitive' => false, 'requires_retention' => false],
            'bank_proof' => ['name' => 'Bank proof', 'sensitive' => true, 'requires_retention' => true],
            'agreement' => ['name' => 'Employment agreement', 'sensitive' => false, 'requires_retention' => false],
        ];
    }
}
