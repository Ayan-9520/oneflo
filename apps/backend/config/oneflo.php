<?php

declare(strict_types=1);

return [

    /*
    | Hosts that serve OneFlo without selecting a tenant. Organization context
    | on these hosts comes from an authorized X-Organization-Id header.
    | A custom hostname selects a tenant only after it is verified in
    | organization_domains. Never add an unverified customer domain here.
    */
    'platform_hosts' => array_values(array_filter(array_map(
        static fn (string $host): string => strtolower(trim($host)),
        explode(',', (string) env('ONEFLO_PLATFORM_HOSTS', 'localhost,127.0.0.1,oneflo.in,www.oneflo.in,app.oneflo.in,api.oneflo.in')),
    ))),

    'frontend_url' => rtrim((string) env('ONEFLO_FRONTEND_URL', 'http://localhost:5173'), '/'),

    /*
    | Candidate sign-in for the public careers site. Providers stay hidden
    | until their client id and secret are set. Mobile codes need an SMS
    | driver; "log" only writes codes to the application log and is refused
    | outside the local environment.
    */
    'candidate_auth' => [
        'google' => [
            'client_id' => env('CANDIDATE_GOOGLE_CLIENT_ID'),
            'client_secret' => env('CANDIDATE_GOOGLE_CLIENT_SECRET'),
        ],
        'linkedin' => [
            'client_id' => env('CANDIDATE_LINKEDIN_CLIENT_ID'),
            'client_secret' => env('CANDIDATE_LINKEDIN_CLIENT_SECRET'),
        ],
        'phone_enabled' => (bool) env('CANDIDATE_PHONE_LOGIN', false),
        'sms_driver' => env('CANDIDATE_SMS_DRIVER', 'log'),
        'token_days' => 30,
    ],

];
