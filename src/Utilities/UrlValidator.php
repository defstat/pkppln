<?php

declare(strict_types=1);

/*
 * (c) 2020 Michael Joyce <mjoyce@sfu.ca>
 * This source file is subject to the GPL v2, bundled
 * with this source code in the file LICENSE.
 */

namespace App\Utilities;

/**
 * Validates URLs
 */
class UrlValidator
{
    /**
     * Retrieves whether the URL is acceptable by the system
     * @param string[] $forbiddenHosts
     */
    public static function isValid(?string $url, array $forbiddenHosts = []): bool
    {
        $url = parse_url((string) $url);
        $host = $url['host'] ?? null;
        $scheme = $url['scheme'] ?? null;
        return $url !== false
            && $host !== null
            && $scheme !== null
            // Blocks IPv6
            && !preg_match('/:/', $host)
            // Blocks IP addresses
            && !preg_match('/^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$/', $host)
            // Blocks top level domains (localhost, local network name, aliases)
            && preg_match('/\./', $host)
            // Blocks forbidden hosts
            && (!\count($forbiddenHosts) || !preg_match('/^(?:' . implode('|', array_map(fn (string $host) => preg_quote($host, '/'), $forbiddenHosts)) . ')$/i', $host))
            // Blocks non-http schemes
            && preg_match('/^https?$/i', $scheme);
    }
}
