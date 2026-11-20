<?php

declare(strict_types=1);

/**
 * Suggested master templates. They are not created inside a business until HR activates them.
 *
 * @return array{departments: array<string, list<string>>, designations: array<string, list<string>>}
 */
return [
    'departments' => [
        'Corporate' => ['Administration', 'Human Resources', 'Finance & Accounts', 'IT', 'Software Development', 'Quality Assurance', 'Sales', 'Marketing', 'Digital Marketing', 'Operations', 'Customer Support', 'Customer Success', 'Business Development', 'Legal & Compliance', 'Procurement', 'Project Management', 'Research & Development', 'Data & Analytics', 'Training & Development'],
        'HR' => ['Talent Acquisition', 'HR Operations', 'Payroll & Compensation', 'Learning & Development', 'Employee Relations', 'Performance Management', 'Workforce Planning', 'Employee Engagement', 'HR Compliance'],
        'Technology' => ['Frontend Development', 'Backend Development', 'Full Stack Development', 'Mobile App Development', 'UI/UX Design', 'DevOps & Cloud', 'Cybersecurity', 'IT Support', 'Database Administration', 'Product Management', 'AI & Machine Learning', 'Technical Support'],
        'Sales & Marketing' => ['Inside Sales', 'Field Sales', 'Corporate Sales', 'Channel Sales', 'Digital Marketing', 'Social Media Marketing', 'SEO', 'Performance Marketing', 'Content & Creative', 'Brand Management', 'Lead Generation', 'Partnerships'],
        'Finance & Lending' => ['Loan Sales', 'Loan Processing', 'Credit & Underwriting', 'Loan Operations', 'Banking Partnerships', 'DSA & Channel Management', 'Collections', 'Documentation & Verification', 'Financial Advisory', 'Disbursement Operations'],
        'Automotive' => ['Dealer Management', 'Automotive Sales', 'Vehicle Procurement', 'Vehicle Inspection', 'Inventory Management', 'Auction Operations', 'Automotive Finance', 'Logistics & Delivery', 'After-Sales Support', 'Spare Parts & Services'],
        'Business Services' => ['Business Operations', 'Client Management', 'Sales & Partnerships', 'Project Management', 'Research & Strategy', 'Product Development', 'MSME Services', 'Client Onboarding', 'Government Scheme Assistance', 'Compliance Support', 'Business Advisory'],
        'Real Estate' => ['Property Sales', 'Channel Partner Network', 'Property Listings', 'Site Visits & Customer Relations', 'Real Estate Marketing'],
        'Travel & Tourism' => ['Travel Sales', 'Holiday Packages', 'Ticketing & Reservations', 'Tour Operations', 'Corporate Travel'],
    ],
    'designations' => [
        'Leadership' => ['Founder', 'Co-Founder', 'CEO', 'MD', 'COO', 'CFO', 'CTO', 'CMO', 'CHRO', 'CBO', 'Director', 'General Manager', 'Assistant General Manager', 'Senior Manager', 'Manager', 'Assistant Manager', 'Deputy Manager', 'Team Leader', 'Supervisor'],
        'HR' => ['HR Head', 'HR Manager', 'HR Executive', 'HR Generalist', 'HR Business Partner', 'HR Recruiter', 'Talent Acquisition Specialist', 'Recruitment Manager', 'Payroll Manager', 'Payroll Executive', 'HR Operations Executive', 'Training Manager', 'HR Coordinator', 'HR Intern'],
        'Sales' => ['Head of Sales', 'Sales Director', 'Sales Manager', 'Area Sales Manager', 'Regional Sales Manager', 'Branch Sales Manager', 'Business Development Manager', 'Business Development Executive', 'Sales Executive', 'Senior Sales Executive', 'Relationship Manager', 'Key Account Manager', 'Channel Partner Manager', 'Lead Generation Executive', 'Telecaller'],
        'Marketing' => ['Marketing Head', 'Marketing Manager', 'Digital Marketing Manager', 'Digital Marketing Executive', 'SEO Executive', 'Social Media Manager', 'Performance Marketing Manager', 'Content Writer', 'Copywriter', 'Graphic Designer', 'Video Editor', 'Motion Graphics Designer', 'Brand Manager', 'Email Marketing Executive', 'Marketing Intern'],
        'Technology' => ['CTO', 'IT Head', 'IT Manager', 'Engineering Manager', 'Technical Lead', 'Software Architect', 'Frontend Developer', 'React Developer', 'Backend Developer', 'Laravel Developer', 'Node.js Developer', 'Full Stack Developer', 'Python Developer', 'Mobile App Developer', 'UI/UX Designer', 'QA Engineer', 'DevOps Engineer', 'Cloud Engineer', 'Database Administrator', 'System Administrator', 'IT Support Executive'],
        'Finance' => ['Finance Head', 'Finance Manager', 'Accounts Manager', 'Senior Accountant', 'Accountant', 'Accounts Executive', 'Financial Analyst', 'Tax Consultant', 'GST Executive', 'Internal Auditor', 'Billing Executive'],
        'Operations' => ['Operations Head', 'Operations Manager', 'Operations Executive', 'Process Manager', 'Process Executive', 'Admin Manager', 'Administration Executive', 'Procurement Manager', 'Logistics Manager', 'Back Office Executive', 'Data Entry Operator', 'MIS Executive'],
        'Customer Support' => ['Customer Support Head', 'Customer Support Manager', 'Customer Success Manager', 'Customer Relationship Manager', 'Customer Support Executive', 'Customer Care Executive', 'Helpdesk Executive', 'Technical Support Engineer', 'Support Team Leader', 'Quality Analyst'],
        'Product & Analytics' => ['Product Head', 'Product Manager', 'Product Owner', 'Business Analyst', 'Data Analyst', 'Data Scientist', 'Research Analyst', 'Project Manager', 'Project Coordinator', 'AI Engineer', 'Machine Learning Engineer'],
        'Finance & Lending' => ['Loan Sales Manager', 'Loan Officer', 'Loan Advisor', 'Loan Consultant', 'Loan Processing Executive', 'Credit Manager', 'Credit Analyst', 'Credit Underwriter', 'DSA Manager', 'Banking Relationship Manager', 'Loan Operations Executive', 'Documentation Executive', 'Verification Executive', 'Disbursement Executive', 'Collection Executive'],
        'Automotive' => ['Automotive Business Manager', 'Dealer Relationship Manager', 'Dealer Acquisition Executive', 'Dealer Support Executive', 'Vehicle Inspection Executive', 'Vehicle Evaluator', 'Auction Manager', 'Auction Operations Executive', 'Inventory Manager', 'Vehicle Logistics Executive', 'Automotive Finance Executive', 'Service Manager', 'Spare Parts Manager'],
        'Business Services' => ['Business Consultant', 'Business Advisor', 'MSME Consultant', 'Client Onboarding Executive', 'Documentation Executive', 'Compliance Executive', 'Scheme Advisor', 'Project Consultant', 'Partnership Manager', 'Business Research Analyst', 'Client Relationship Manager'],
        'Real Estate' => ['Real Estate Sales Manager', 'Property Consultant', 'Channel Partner Manager', 'Freelance Partner Coordinator', 'Property Listing Executive', 'Site Visit Executive', 'Customer Relationship Executive'],
        'Travel & Tourism' => ['Travel Sales Manager', 'Travel Consultant', 'Holiday Package Executive', 'Ticketing Executive', 'Reservation Executive', 'Tour Operations Executive', 'Corporate Travel Executive'],
    ],
];
