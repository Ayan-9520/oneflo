<?php

declare(strict_types=1);

namespace App\Support;

final class HostName
{
    public static function normalize(?string $host): ?string
    {
        if ($host === null) {
            return null;
        }

        $host = strtolower(rtrim(trim($host), '.'));
        if ($host === '' || strlen($host) > 253 || str_contains($host, ':') || str_contains($host, '/')) {
            return null;
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return $host;
        }

        if (! preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)*[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/', $host)) {
            return null;
        }

        return $host;
    }

    public static function isAddress(string $host): bool
    {
        return filter_var($host, FILTER_VALIDATE_IP) !== false;
    }

    public static function isPlatform(string $host): bool
    {
        $allowed = config('oneflo.platform_hosts', []);

        return is_array($allowed) && in_array($host, $allowed, true);
    }
}
