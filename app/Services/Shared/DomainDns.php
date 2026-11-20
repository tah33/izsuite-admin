<?php

namespace App\Services\Shared;

/**
 * What a domain's DNS says now (Doc 19 section 19.5): the TXT records of a
 * name. Resolved by the container, so tests say what a name holds without
 * reaching the network.
 */
class DomainDns
{
    /** @return list<string> the TXT records of $name - none when it has none, or cannot be read. */
    public function txt(string $name): array
    {
        $records = @dns_get_record($name, DNS_TXT);

        return is_array($records) ? array_values(array_filter(array_map(fn (array $record) => $record['txt'] ?? null, $records))) : [];
    }
}
