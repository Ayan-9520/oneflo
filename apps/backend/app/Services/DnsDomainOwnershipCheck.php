<?php

declare(strict_types=1);

namespace App\Services;

final class DnsDomainOwnershipCheck implements DomainOwnershipCheck
{
    public function owns(string $host, string $token): bool
    {
        $expected = 'oneflo-domain-verification='.$token;
        $records = dns_get_record($host, DNS_TXT);
        if ($records === false) {
            return false;
        }

        foreach ($records as $record) {
            if (($record['txt'] ?? null) === $expected) {
                return true;
            }
            $entries = $record['entries'] ?? [];
            if (is_array($entries) && in_array($expected, $entries, true)) {
                return true;
            }
        }

        return false;
    }
}
