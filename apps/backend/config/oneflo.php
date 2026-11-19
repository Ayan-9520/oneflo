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

];
