<?php

declare(strict_types=1);

namespace App\Support;

final class DocumentLetterBodies
{
    public static function for(string $type): string
    {
        $opening = "Date: {{document.date}}\nNumber: {{document.number}}\n\nTo {{employee.given_name}} {{employee.family_name}}\nEmployee code: {{employee.employee_code}}\n";

        $role = "Role: {{employee.designation}}\nDepartment: {{employee.department}}\nWork location: {{employee.work_location}}\nJoining date: {{employee.joining_date}}\nReporting manager: {{employee.manager}}\n";

        $pay = "Annual fixed pay: {{employee.annual_fixed}}\nAnnual variable pay: {{employee.annual_variable}}\nAnnual cost to company: {{employee.annual_ctc}}\n";

        $close = "\nThis letter is issued by {{company.legal_name}}.\nContact: {{company.email}} {{company.phone}}\nWebsite: {{company.website}}\n";

        $body = match ($type) {
            DocumentTypes::OFFER_LETTER => <<<'LETTER'
Company name: {{company.legal_name}}
Address: {{company.address}}
Contact: {{company.phone}}
Website: {{company.website}}
Email: {{company.email}}
Date: {{document.date}}

{{employee.full_name}}
Employee code: {{employee.employee_code}}

Re: Offer of employment

Dear {{employee.full_name}},

We are pleased to offer you employment at {{company.legal_name}} {{employee.position_phrase}}. This offer uses only the details saved on the company and employee records.

Your compensation package

{{employee.compensation_paragraph}}

You may be eligible for benefits such as mobile, internet, and local conveyance, as per company policy, depending on your role and the nature of work.

Joining date

{{employee.joining_sentence}} Please join on or before that date. If you do not join by the recorded date, this offer can be withdrawn.

Pre-employment requirements

Before joining, you are required to sign a non-disclosure agreement.

Documents required for joining

Please bring these documents when you join:
- Copies of your educational certificates.

Yours truly,
LETTER,
            DocumentTypes::APPOINTMENT_LETTER => "Subject: Appointment\n\n{{company.legal_name}} appoints you on the terms recorded below.\n\n".$role.$pay,
            DocumentTypes::JOINING_LETTER => "Subject: Joining\n\n{{company.legal_name}} records that you are joining on the date shown below.\n\n".$role,
            DocumentTypes::INTERNSHIP_OFFER => "Subject: Internship offer\n\n{{company.legal_name}} offers you an internship on the terms recorded below. This is not a confirmation of employment after the internship.\n\n".$role,
            DocumentTypes::INTERVIEW_CALL_LETTER => "Subject: Interview\n\n{{company.legal_name}} asks you to attend an interview for the role recorded below. The date, time, and place are shared separately when they are fixed.\n\nRole: {{employee.designation}}\nWork location: {{employee.work_location}}\n",
            DocumentTypes::GENERAL_LETTER => "Subject: {{company.legal_name}}\n\nThis letter is addressed to you in connection with your record at {{company.legal_name}}.\n\n".$role,
            DocumentTypes::EMPLOYMENT_AGREEMENT => "Subject: Employment agreement\n\nThis agreement records the employment terms saved for you at {{company.legal_name}}. It does not add terms that are not on your record.\n\n".$role.$pay,
            DocumentTypes::CONFIRMATION_LETTER => "Subject: Confirmation of employment\n\n{{company.legal_name}} confirms your employment on the record below.\n\n".$role,
            DocumentTypes::INCREMENT_LETTER => "Subject: Pay revision\n\n{{company.legal_name}} records the pay saved on your salary structure. Amounts stay blank when no structure is saved.\n\n".$role.$pay,
            DocumentTypes::PROMOTION_LETTER => "Subject: Promotion\n\n{{company.legal_name}} records the role saved on your employment record.\n\n".$role.$pay,
            DocumentTypes::EXPERIENCE_CERTIFICATE => "Subject: Experience\n\n{{company.legal_name}} confirms that {{employee.given_name}} {{employee.family_name}} is associated with the organisation on the record below.\n\n".$role,
            DocumentTypes::SALARY_CERTIFICATE => "Subject: Salary\n\n{{company.legal_name}} confirms the pay saved on the salary structure. Amounts stay blank when no structure is saved.\n\n".$role.$pay,
            DocumentTypes::RELIEVING_LETTER => "Subject: Relieving\n\n{{company.legal_name}} records your employment as saved below. The last working date is the date stored on the employment record when one has been saved.\n\n".$role,
            DocumentTypes::WARNING_LETTER => "Subject: Warning\n\n{{company.legal_name}} issues this warning against the employment record below. The specific incident must be added in the saved template before this letter is used for a real warning.\n\n".$role,
            DocumentTypes::RESIGNATION_ACCEPTANCE => "Subject: Resignation acceptance\n\n{{company.legal_name}} acknowledges the resignation recorded for the employment below.\n\n".$role,
            DocumentTypes::NO_OBJECTION_CERTIFICATE => "Subject: No objection\n\n{{company.legal_name}} records that it has no objection on file for the employment below, limited to the details saved on the record.\n\n".$role,
            DocumentTypes::INTERNSHIP_COMPLETION => "Subject: Internship completion\n\n{{company.legal_name}} records that the internship below is complete as saved on the record.\n\n".$role,
            DocumentTypes::TRANSFER_LETTER => "Subject: Transfer\n\n{{company.legal_name}} records the work location and role saved on your employment record.\n\n".$role,
            DocumentTypes::AUTHORIZATION_LETTER => "Subject: Authorization\n\n{{company.legal_name}} authorises {{employee.given_name}} {{employee.family_name}} to act only in the role saved below. Any narrower authority must be written into the saved template before issue.\n\n".$role,
            DocumentTypes::EMPLOYMENT_VERIFICATION => "Subject: Employment verification\n\n{{company.legal_name}} verifies the employment details saved below. Details that are not on the record are left blank.\n\n".$role.$pay,
            default => "{{company.legal_name}} issues this letter to {{employee.given_name}} {{employee.family_name}}.\n",
        };

        return $opening.$body.$close;
    }
}
