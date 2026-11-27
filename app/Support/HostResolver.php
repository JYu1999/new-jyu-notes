<?php

namespace App\Support;

/** DNS lookup, behind a class so tests can stub it. */
class HostResolver
{
    /** @return list<string> IPv4 / IPv6 addresses (empty when the name doesn't resolve) */
    public function resolve(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }

        $ips = [];
        foreach (@dns_get_record($host, DNS_A | DNS_AAAA) ?: [] as $record) {
            $ips[] = $record['ip'] ?? $record['ipv6'] ?? null;
        }

        return array_values(array_filter($ips));
    }
}
