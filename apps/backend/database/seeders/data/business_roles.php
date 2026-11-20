<?php

declare(strict_types=1);

/**
 * Department => designations activated for each business from the workforce master templates.
 * Names must exist in workforce_master.php.
 *
 * @return array{common: array<string, list<string>>, businesses: array<string, array<string, list<string>>>}
 */
return [
    'common' => [
        'Human Resources' => ['HR Manager', 'HR Executive', 'HR Recruiter'],
        'Finance & Accounts' => ['Accounts Manager', 'Accountant'],
        'Sales' => ['Sales Manager', 'Sales Executive', 'Business Development Executive', 'Telecaller'],
        'Marketing' => ['Digital Marketing Executive', 'Graphic Designer', 'Content Writer'],
        'Operations' => ['Operations Manager', 'Operations Executive', 'Back Office Executive'],
        'Customer Support' => ['Customer Support Executive', 'Support Team Leader'],
        'IT' => ['IT Support Executive', 'Full Stack Developer'],
    ],
    'businesses' => [
        'rupeedial' => [
            'Loan Sales' => ['Loan Sales Manager', 'Loan Advisor', 'Loan Officer'],
            'Credit & Underwriting' => ['Credit Manager', 'Credit Analyst'],
            'Loan Operations' => ['Loan Processing Executive', 'Disbursement Executive', 'Verification Executive'],
            'Collections' => ['Collection Executive'],
        ],
        'motorcart' => [
            'Dealer Management' => ['Dealer Relationship Manager', 'Dealer Acquisition Executive'],
            'Vehicle Inspection' => ['Vehicle Inspection Executive', 'Vehicle Evaluator'],
            'Auction Operations' => ['Auction Manager', 'Auction Operations Executive'],
            'After-Sales Support' => ['Service Manager', 'Spare Parts Manager'],
        ],
        'propancer' => [
            'Property Sales' => ['Real Estate Sales Manager', 'Property Consultant'],
            'Channel Partner Network' => ['Channel Partner Manager', 'Freelance Partner Coordinator'],
            'Property Listings' => ['Property Listing Executive'],
            'Site Visits & Customer Relations' => ['Site Visit Executive', 'Customer Relationship Executive'],
        ],
        'udhyamsetu' => [
            'MSME Services' => ['MSME Consultant', 'Business Advisor'],
            'Client Onboarding' => ['Client Onboarding Executive'],
            'Government Scheme Assistance' => ['Scheme Advisor'],
            'Compliance Support' => ['Compliance Executive'],
        ],
        'travelbridge' => [
            'Travel Sales' => ['Travel Sales Manager', 'Travel Consultant'],
            'Holiday Packages' => ['Holiday Package Executive'],
            'Ticketing & Reservations' => ['Ticketing Executive', 'Reservation Executive'],
            'Tour Operations' => ['Tour Operations Executive'],
        ],
    ],
];
