<?php

declare(strict_types=1);

namespace App\Support;

final class DocumentTypes
{
    public const OFFER_LETTER = 'offer_letter';

    public const APPOINTMENT_LETTER = 'appointment_letter';

    public const GENERAL_LETTER = 'general_letter';

    public const EXPERIENCE_CERTIFICATE = 'experience_certificate';

    public const SALARY_CERTIFICATE = 'salary_certificate';

    public const RELIEVING_LETTER = 'relieving_letter';

    public const EMPLOYMENT_AGREEMENT = 'employment_agreement';

    public const JOINING_LETTER = 'joining_letter';

    public const CONFIRMATION_LETTER = 'confirmation_letter';

    public const INCREMENT_LETTER = 'increment_letter';

    public const PROMOTION_LETTER = 'promotion_letter';

    public const WARNING_LETTER = 'warning_letter';

    public const RESIGNATION_ACCEPTANCE = 'resignation_acceptance';

    public const NO_OBJECTION_CERTIFICATE = 'no_objection_certificate';

    public const INTERNSHIP_OFFER = 'internship_offer';

    public const INTERNSHIP_COMPLETION = 'internship_completion';

    public const TRANSFER_LETTER = 'transfer_letter';

    public const INTERVIEW_CALL_LETTER = 'interview_call_letter';

    public const AUTHORIZATION_LETTER = 'authorization_letter';

    public const EMPLOYMENT_VERIFICATION = 'employment_verification';

    /**
     * @return array<string, array{label: string, official: bool}>
     */
    public static function catalog(): array
    {
        return [
            self::OFFER_LETTER => ['label' => 'Offer letter', 'official' => false],
            self::APPOINTMENT_LETTER => ['label' => 'Appointment letter', 'official' => false],
            self::JOINING_LETTER => ['label' => 'Joining letter', 'official' => false],
            self::GENERAL_LETTER => ['label' => 'General letter', 'official' => false],
            self::EMPLOYMENT_AGREEMENT => ['label' => 'Employment agreement', 'official' => true],
            self::CONFIRMATION_LETTER => ['label' => 'Confirmation letter', 'official' => true],
            self::INCREMENT_LETTER => ['label' => 'Increment letter', 'official' => true],
            self::PROMOTION_LETTER => ['label' => 'Promotion letter', 'official' => true],
            self::EXPERIENCE_CERTIFICATE => ['label' => 'Experience certificate', 'official' => true],
            self::SALARY_CERTIFICATE => ['label' => 'Salary certificate', 'official' => true],
            self::RELIEVING_LETTER => ['label' => 'Relieving letter', 'official' => true],
            self::WARNING_LETTER => ['label' => 'Warning letter', 'official' => true],
            self::RESIGNATION_ACCEPTANCE => ['label' => 'Resignation acceptance', 'official' => true],
            self::NO_OBJECTION_CERTIFICATE => ['label' => 'No-objection certificate', 'official' => true],
            self::INTERNSHIP_OFFER => ['label' => 'Internship offer', 'official' => false],
            self::INTERNSHIP_COMPLETION => ['label' => 'Internship completion', 'official' => true],
            self::TRANSFER_LETTER => ['label' => 'Transfer letter', 'official' => true],
            self::INTERVIEW_CALL_LETTER => ['label' => 'Interview call letter', 'official' => false],
            self::AUTHORIZATION_LETTER => ['label' => 'Authorization letter', 'official' => true],
            self::EMPLOYMENT_VERIFICATION => ['label' => 'Employment verification', 'official' => true],
        ];
    }

    public static function isOfficial(string $type): bool
    {
        return (bool) (self::catalog()[$type]['official'] ?? false);
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(self::catalog());
    }
}
